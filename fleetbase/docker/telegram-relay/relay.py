"""
Telegram SMS-relay for Fleetbase (test scope only).

Receives {to, text} from Fleetbase's custom_http SMS provider,
checks the phone against an explicit allow-list mapping, and
forwards the message to the matching Telegram chat_id via the
Telegram Bot API. Any phone not in the map is rejected (fails
loudly instead of silently swallowing real driver codes).
"""
import json
import os
import urllib.request
from http.server import BaseHTTPRequestHandler, HTTPServer

BOT_TOKEN = os.environ["TELEGRAM_BOT_TOKEN"]

# Explicit, hardcoded allow-list: phone (E.164) -> Telegram chat_id.
# Deliberately NOT wildcard/global — only mapped numbers are routed here.
PHONE_TO_CHAT = {
    os.environ.get("TELEGRAM_TEST_PHONE", "+380674105077"):
        os.environ.get("TELEGRAM_TEST_CHAT_ID", "1011966727"),
}

TELEGRAM_API = f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage"


class Handler(BaseHTTPRequestHandler):
    def _json(self, status, payload):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        if self.path != "/relay":
            return self._json(404, {"error": "not found"})

        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length) if length else b""
        ctype = self.headers.get("Content-Type", "")

        if "application/json" in ctype:
            data = json.loads(raw or b"{}")
        else:
            from urllib.parse import parse_qsl
            data = dict(parse_qsl(raw.decode("utf-8")))

        to = data.get("to", "")
        text = data.get("text", "")
        chat_id = PHONE_TO_CHAT.get(to)

        if not chat_id:
            print(f"[telegram-relay] REJECTED unmapped phone: {to!r}")
            return self._json(422, {
                "success": False,
                "error": f"No Telegram mapping for phone {to}",
            })

        tg_body = json.dumps({"chat_id": chat_id, "text": text}).encode("utf-8")
        req = urllib.request.Request(
            TELEGRAM_API, data=tg_body,
            headers={"Content-Type": "application/json"}, method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=10) as resp:
                tg_result = json.loads(resp.read())
        except Exception as e:
            print(f"[telegram-relay] Telegram API error: {e}")
            return self._json(502, {"success": False, "error": str(e)})

        if not tg_result.get("ok"):
            return self._json(502, {"success": False, "error": tg_result})

        print(f"[telegram-relay] Sent to chat_id={chat_id} for phone={to}")
        return self._json(200, {
            "success": True,
            "message_id": tg_result["result"]["message_id"],
            "status": "sent",
        })

    def log_message(self, fmt, *args):
        print("[telegram-relay] %s - %s" % (self.address_string(), fmt % args))


if __name__ == "__main__":
    port = int(os.environ.get("PORT", "9000"))
    print(f"[telegram-relay] listening on 0.0.0.0:{port}")
    HTTPServer(("0.0.0.0", port), Handler).serve_forever()
