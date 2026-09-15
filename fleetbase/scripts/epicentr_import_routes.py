# -*- coding: utf-8 -*-
"""
Импорт "Маршрутних листів" (xlsx) в Fleetbase: создаёт Places (точки
доставки) и Orders, назначает водителя по номеру авто.

Источники истины, использованные при написании (см. чат):
- Fleetbase Orders API:  POST/GET /v1/orders   (fleetbase.io/docs/api/fleetbase/orders)
- Fleetbase Places API:  POST/GET /v1/places   (fleetbase.io/docs/api/fleetbase/places)
- Fleetbase Drivers API: GET /v1/drivers       (fleetbase.io/docs/api/fleetbase/drivers)
- Геокодинг: Fleetbase сам умеет геокодить, но ТОЛЬКО через Google Maps
  (api/vendor/fleetbase/fleetops-api/server/src/Support/Geocoding.php),
  а GOOGLE_MAPS_API_KEY в api/.env сейчас пуст -> автогеокодинг в Fleetbase
  не работает. Поэтому координаты считает сам импортёр через публичный
  Nominatim (OpenStreetMap) и передаёт их в Fleetbase явно (location.lat/lng),
  как это документировано в Places API ("Create a Place using Coordinates").
"""
import json
import os
import re
import sys
import time
import urllib.parse
import urllib.request

import openpyxl

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")

FLEETBASE_BASE = os.environ.get("FLEETBASE_BASE", "http://localhost:18080/v1")
FLEETBASE_KEY = os.environ.get("FLEETBASE_KEY", "")
NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"
NOMINATIM_UA = "epicentr-delivery-import/1.0 (dispatcher tool; contact: internal)"

# Известные склады (public_id из Fleetbase, уже созданы вручную с координатами).
# Сопоставление по подстроке названия склада из файла -> place_id в Fleetbase.
WAREHOUSE_MAP = {
    "сокільники": "place_myoijrhwyc",       # Львів - Сокільники (ТРЦ)
    "хмельницького": "place_fto8gvns3o",     # Львів - Хмельницького 188А
    "стартова": "place_kj0lup6ynt",          # Дніпро - Стартова (Рефрижератори)
    "бабенка": "place_jjd2p5kkil",           # Дніпро - Адресна (Бабенка)
    "черкаси": "place_g9xl9d2ne6",           # Черкаси - Адресна
    "полярна": "place_g4tgh3hs8r",           # Київ - Полярна
    "кільцева": "place_xpgwvyhatp",          # Київ 2 - Велика Кільцева
}


def api(method, path, body=None):
    url = f"{FLEETBASE_BASE}{path}"
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(url, data=data, method=method, headers={
        "Authorization": f"Bearer {FLEETBASE_KEY}",
        "Content-Type": "application/json",
        "Accept": "application/json",
    })
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            return resp.status, json.loads(resp.read() or b"{}")
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read() or b"{}")


_geocode_cache = {}


def geocode(address):
    """Nominatim public API. 1 req/sec, обязательный User-Agent (usage policy)."""
    if address in _geocode_cache:
        return _geocode_cache[address]
    qs = urllib.parse.urlencode({
        "q": address, "format": "json", "addressdetails": 1,
        "countrycodes": "ua", "limit": 1,
    })
    req = urllib.request.Request(f"{NOMINATIM_URL}?{qs}", headers={"User-Agent": NOMINATIM_UA})
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            results = json.loads(resp.read())
    except Exception as e:
        print(f"    [geocode ERROR] {address!r}: {e}")
        results = []
    time.sleep(1.1)  # Nominatim fair-use: max 1 req/sec
    out = None
    if results:
        out = (float(results[0]["lat"]), float(results[0]["lon"]), results[0].get("display_name"))
    _geocode_cache[address] = out
    return out


def find_label_value(rows, label_text):
    """Ищет ячейку, содержащую label_text, и возвращает первое непустое
    значение правее неё в той же строке. Устойчиво к сдвигу строк/столбцов
    между разными выгрузками 'Маршрутних листів' (проверено на реальных
    файлах: положение шапки плавает на 1 строку между экспортами)."""
    for row in rows:
        for j, v in enumerate(row):
            if v is None:
                continue
            if label_text in str(v):
                for k in range(j + 1, len(row)):
                    if row[k] not in (None, ""):
                        return row[k]
    return None


def parse_route_sheet(path):
    wb = openpyxl.load_workbook(path, data_only=True)
    ws = wb.worksheets[0]
    rows = list(ws.iter_rows(values_only=True))

    def clean(v):
        return str(v).strip() if v is not None else None

    meta = {
        "route_no": clean(find_label_value(rows, "Маршрутний лист")),
        "warehouse": clean(find_label_value(rows, "Склад:")),
        "date": clean(find_label_value(rows, "Дата:")),
        "carrier": clean(find_label_value(rows, "Перевізник:")),
        "driver_name": clean(find_label_value(rows, "Водій:")),
        "vehicle": clean(find_label_value(rows, "Автомобіль:")),
    }

    header_row_idx = None
    for i, row in enumerate(rows):
        if row[0] == "№" and "Код точки" in row:
            header_row_idx = i
            break
    if header_row_idx is None:
        raise ValueError("Не найдена строка заголовков ('Код точки')")

    orders = []
    for row in rows[header_row_idx + 1:]:
        code_tochki = row[1]
        # Строка "Разом:" и подвал ("Особливі відмітки:", "Вантаж прийняв...")
        # тоже попадают в колонку 1 текстом -> отсекаем по признаку "код точки
        # состоит только из цифр" (реальные коды точек Эпицентра - числовые).
        if code_tochki is None:
            continue
        if not str(code_tochki).strip().isdigit():
            break
        adresa_col = row[5]
        comment = row[27] if len(row) > 27 else None
        m = re.search(r"Адреса доставки:\s*(.+)$", comment or "", re.IGNORECASE)
        full_address = m.group(1).strip() if m else adresa_col
        phone_field = row[35] if len(row) > 35 else None
        phone_m = re.search(r"(\+380\d{9})", phone_field or "")
        orders.append({
            "code_tochki": str(code_tochki).strip(),
            "address_short": adresa_col,
            "address_full": full_address,
            "weight": row[17],
            "max_item_weight": row[19],
            "volume": row[21],
            "payment_form": row[13],
            "time_window": f"{row[25]}-{row[26]}" if row[25] else None,
            "recipient_phone": phone_m.group(1) if phone_m else None,
            "recipient_raw": phone_field,
            "driver_comment": comment,
        })
    return meta, orders


def find_warehouse_place(warehouse_name):
    if not warehouse_name:
        return None
    low = warehouse_name.lower()
    for key, place_id in WAREHOUSE_MAP.items():
        if key in low:
            return place_id
    return None


def find_driver_by_plate(plate_fragment, drivers):
    if not plate_fragment:
        return None
    # "BC3416EX MERCEDES BENZ" -> ищем "BC3416EX" среди vehicle.plate_number
    plate_guess = plate_fragment.split()[0].upper()
    for d in drivers:
        v = d.get("vehicle") or {}
        if v.get("plate_number", "").upper() == plate_guess:
            return d
    return None


def run(files, dry_run=True):
    status, drivers = api("GET", "/drivers?limit=100")
    if status != 200:
        print("Не удалось получить список водителей:", drivers)
        return
    print(f"Загружено водителей: {len(drivers)}\n")

    plan = []
    for path in files:
        meta, orders = parse_route_sheet(path)
        print(f"=== {os.path.basename(path)} ===")
        print(f"  Маршрутний лист №{meta['route_no']}, дата {meta['date']}, "
              f"водій «{meta['driver_name']}», авто «{meta['vehicle']}», склад «{meta['warehouse']}»")

        pickup_id = find_warehouse_place(meta["warehouse"])
        driver = find_driver_by_plate(meta["vehicle"], drivers)

        print(f"  -> склад в Fleetbase: {pickup_id or 'НЕ НАЙДЕН'}")
        print(f"  -> водитель в Fleetbase: {driver['name'] + ' (' + driver['id'] + ')' if driver else 'НЕ НАЙДЕН'}")
        print(f"  -> строк-заказов в файле: {len(orders)}\n")

        for o in orders:
            geo = geocode(o["address_full"])
            row_plan = {
                "route_no": meta["route_no"],
                "date": meta["date"],
                "pickup_id": pickup_id,
                "driver_id": driver["id"] if driver else None,
                "driver_name": driver["name"] if driver else None,
                **o,
                "geo": geo,
            }
            plan.append(row_plan)
            status_str = "OK" if geo else "ГЕОКОДИНГ НЕ УДАЛСЯ"
            print(f"    [{o['code_tochki']}] {o['address_full'][:70]:<70} -> {status_str}"
                  + (f"  ({geo[0]:.5f},{geo[1]:.5f})" if geo else ""))
        print()

    ok = sum(1 for p in plan if p["geo"] and p["pickup_id"] and p["driver_id"])
    print(f"ИТОГО строк: {len(plan)}, готовы к созданию: {ok}, требуют внимания: {len(plan) - ok}")

    if dry_run:
        print("\n(это DRY-RUN — ничего не создано и не изменено в Fleetbase)")
        return plan

    created = []
    for p in plan:
        if not (p["geo"] and p["pickup_id"] and p["driver_id"]):
            print(f"  ПРОПУСК [{p['code_tochki']}]: не хватает данных для создания заказа")
            continue
        lat, lon, display_name = p["geo"]
        s_status, search_res = api("GET", f"/places?query={urllib.parse.quote(p['address_full'])}&limit=1")
        place_id = None
        if s_status == 200 and isinstance(search_res, list) and search_res:
            place_id = search_res[0]["id"]
        else:
            place_body = {
                "name": p["address_short"] or p["address_full"],
                "address": p["address_full"],
                "location": {"latitude": lat, "longitude": lon},
                "type": "dropoff",
                "phone": p["recipient_phone"],
            }
            p_status, p_res = api("POST", "/places", place_body)
            if p_status not in (200, 201):
                print(f"  ОШИБКА создания Place [{p['code_tochki']}]: {p_res}")
                continue
            place_id = p_res["id"]

        order_body = {
            "pickup": p["pickup_id"],
            "dropoff": place_id,
            "driver": p["driver_id"],
            "internal_id": p["code_tochki"],
            "dispatch": False,
            "notes": (p["driver_comment"] or "")[:1000],
            "meta": {
                "route_no": p["route_no"],
                "weight_kg": p["weight"],
                "volume_m3": p["volume"],
                "payment_form": p["payment_form"],
                "time_window": p["time_window"],
            },
        }
        if p["recipient_phone"]:
            order_body["customer"] = {
                "name": (p["recipient_raw"] or "").strip(),
                "phone": p["recipient_phone"],
            }
        o_status, o_res = api("POST", "/orders", order_body)
        if o_status not in (200, 201):
            print(f"  ОШИБКА создания Order [{p['code_tochki']}]: {o_res}")
            continue
        print(f"  СОЗДАНО [{p['code_tochki']}] -> order {o_res['id']}")
        created.append(o_res["id"])

    print(f"\nСоздано заказов: {len(created)}")
    return created


if __name__ == "__main__":
    files = sys.argv[1:-1] if len(sys.argv) > 2 and sys.argv[-1] in ("--live", "--dry-run") else sys.argv[1:]
    live = "--live" in sys.argv
    run(files, dry_run=not live)
