<?php
require "/fleetbase/api/vendor/autoload.php";
$app = require "/fleetbase/api/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\Ledger\Models\Invoice;
use Fleetbase\Ledger\Models\InvoiceItem;
use Fleetbase\Ledger\Services\InvoiceService;

$companyUuid = '4aa20fe8-f693-428f-816f-8cf91ac1ea48'; // PE Egorov D.V.

try {
    session(['company' => $companyUuid]);
} catch (\Throwable $e) {
    echo "session() warning: " . $e->getMessage() . PHP_EOL;
}

$contact = Contact::where('company_uuid', $companyUuid)
    ->where(function ($q) {
        $q->where('name', 'like', '%ЕПІЦЕНТР%')
          ->orWhere('meta->edrpou', '32490244');
    })
    ->first();

if (!$contact) {
    $contact = Contact::create([
        'company_uuid' => $companyUuid,
        'name'         => 'ТОВ «ЕПІЦЕНТР К»',
        'type'         => 'customer',
        'meta'         => [
            'edrpou'   => '32490244',
            'itn'      => '324902426531',
            'address'  => '04128, м. Київ, вул. Берковецька, буд. 6-К',
            'contract' => '№ 25/06/2026-ДЕ від 25.06.2026',
        ],
    ]);
    echo "CREATED_CONTACT " . $contact->uuid . PHP_EOL;
} else {
    echo "FOUND_CONTACT " . $contact->uuid . PHP_EOL;
}

// Sample: Рахунок + Акт № 85 від 09.09.2026 (data read from source .xlsx via openpyxl)
$items = [
    ['desc' => 'Транспортні послуги а/м CA4951CM CITROEN JUMPER, м. Черкаси, 07.09.2026 (код НФ-00000327)', 'qty' => 1, 'price' => 5000.00],
    ['desc' => 'Вантажні послуги занос на поверх, 07.09.2026 (код НФ-00000329)', 'qty' => 1, 'price' => 320.00],
    ['desc' => 'Транспортні послуги а/м CA4951CM CITROEN JUMPER, м. Черкаси, 08.09.2026 (код НФ-00000328)', 'qty' => 1, 'price' => 5000.00],
    ['desc' => 'Вантажні послуги занос на поверх, 08.09.2026 (код НФ-00000330)', 'qty' => 1, 'price' => 380.00],
    ['desc' => 'Експедиційні послуги (код НФ-00000002)', 'qty' => 2, 'price' => 100.00],
];

$invoice = Invoice::create([
    'company_uuid'  => $companyUuid,
    'customer_uuid' => $contact->uuid,
    'customer_type' => Contact::class,
    'date'          => '2026-09-09',
    'currency'      => 'UAH',
    'status'        => 'draft',
    'notes'         => 'Джерело: Рахунок на оплату №85 від 09.09.2026 + Акт про надання послуг №85 від 09.09.2026. Підписант з боку Замовника (Акт): Шевченко Тарас Володимирович. Договір № 25/06/2026-ДЕ від 25.06.2026.',
    'meta'          => [
        'source_number' => 'НФ-85',
        'import_source' => 'epicenter_import_sample',
    ],
]);

foreach ($items as $it) {
    $item = new InvoiceItem([
        'invoice_uuid' => $invoice->uuid,
        'description'  => $it['desc'],
        'quantity'     => $it['qty'],
        'unit_price'   => sprintf('%.2f', $it['price']),
        'tax_rate'     => 0,
    ]);
    $item->calculateAmount();
    $item->save();
}

$invoice->calculateTotals();
$invoice->save();

try {
    app(InvoiceService::class)->recogniseRevenue($invoice);
    echo "REVENUE_RECOGNISED" . PHP_EOL;
} catch (\Throwable $e) {
    echo "REVENUE_ERROR " . $e->getMessage() . PHP_EOL;
}

$invoice->refresh();
$invoice->load('items');

echo "INVOICE_NUMBER " . $invoice->number . PHP_EOL;
echo "INVOICE_UUID " . $invoice->uuid . PHP_EOL;
echo "INVOICE_PUBLIC_ID " . $invoice->public_id . PHP_EOL;
echo "TOTAL " . $invoice->total_amount . " " . $invoice->currency . PHP_EOL;
echo "SUBTOTAL " . $invoice->subtotal . PHP_EOL;
foreach ($invoice->items as $i) {
    echo "ITEM | " . $i->description . " | qty=" . $i->quantity . " price=" . $i->unit_price . " amount=" . $i->amount . PHP_EOL;
}
