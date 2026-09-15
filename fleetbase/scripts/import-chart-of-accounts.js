/**
 * import-chart-of-accounts.js
 * ------------------------------------------------------------------
 * Bulk-imports additional Chart-of-Accounts entries into Fleetbase's
 * Ledger module for THIS specific company (ФОП Єгоров Д. В.).
 *
 * IMPORTANT — corrections vs. the "Gemini" instructions that were pasted in:
 *   1. There is NO public "/v1/ledger/accounts" endpoint reachable with a
 *      Secret API Key (sk_live_...). That key type only authenticates the
 *      wallet endpoints (ledger/v1/wallet*). Verified against
 *      packages/ledger/server/src/routes.php.
 *   2. Accounts (Chart of Accounts) live under the INTERNAL, session-protected
 *      routes:  POST /ledger/int/v1/accounts  (auth:sanctum).
 *      Confirmed with: php artisan route:list --path=ledger/int/v1/accounts
 *   3. To call a protected route from a script, you first log in via
 *      POST /int/v1/auth/login with {identity, password} to get a
 *      Sanctum bearer token, then send that token on every request.
 *   4. Fleetbase already auto-seeds a system Chart of Accounts per company
 *      (CASH-DEFAULT, AR-DEFAULT, REVENUE-DELIVERY, etc. — all in USD).
 *      This script does NOT duplicate those. It only adds the UAH-denominated
 *      accounts specific to this ФОП's actual invoice line items.
 *
 * USAGE:
 *   set FLEETBASE_IDENTITY=your-login-email
 *   set FLEETBASE_PASSWORD=your-password
 *   node import-chart-of-accounts.js
 *
 * (On PowerShell use:  $env:FLEETBASE_IDENTITY="..."; $env:FLEETBASE_PASSWORD="..."; node import-chart-of-accounts.js)
 *
 * If the env vars are not set, the script will prompt for them interactively
 * (password input is not masked in a plain Node prompt — prefer the env vars
 * on a shared machine).
 */

import readline from 'node:readline/promises';
import { stdin as input, stdout as output } from 'node:process';

const API_BASE = process.env.FLEETBASE_API_BASE || 'http://192.168.0.101:18080';

// ── Accounts to create ──────────────────────────────────────────────
// Derived from the actual invoice (НФ-87 от 09.09.2026): transport,
// loader, carry-up, and freight-forwarding services — all in UAH.
// Codes deliberately avoid the existing system codes (CASH-DEFAULT, etc.)
const accountsToImport = [
    { name: 'Поточний рахунок ПАТ "КРЕДОБАНК" (UAH)', type: 'asset', code: '1010-UAH', currency: 'UAH', description: 'р/р UA673253650000000260030043249' },
    { name: 'Дебіторська заборгованість (грн)', type: 'asset', code: '1200-UAH', currency: 'UAH', description: 'AR у гривні, окремо від системного AR-DEFAULT (USD)' },
    { name: 'Дохід від транспортних послуг', type: 'revenue', code: '4100-UAH', currency: 'UAH' },
    { name: 'Дохід від послуг вантажника', type: 'revenue', code: '4110-UAH', currency: 'UAH' },
    { name: 'Дохід від експедиційних послуг', type: 'revenue', code: '4120-UAH', currency: 'UAH' },
    { name: 'Паливні витрати', type: 'expense', code: '5100-UAH', currency: 'UAH' },
    { name: 'Обслуговування автотранспорту', type: 'expense', code: '5110-UAH', currency: 'UAH' },
];

async function prompt(question, hidden = false) {
    const rl = readline.createInterface({ input, output });
    const answer = await rl.question(question);
    rl.close();
    return answer.trim();
}

async function login() {
    const identity = process.env.FLEETBASE_IDENTITY || (await prompt('Email/username: '));
    const password = process.env.FLEETBASE_PASSWORD || (await prompt('Password: '));

    const res = await fetch(`${API_BASE}/int/v1/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identity, password }),
    });

    const data = await res.json();

    if (!res.ok || !data.token) {
        console.error('[ОШИБКА ВХОДА]', data);
        if (data.twoFaSession) {
            console.error('На этом аккаунте включена 2FA — этот скрипт не поддерживает код подтверждения. Войдите через консоль или временно отключите 2FA.');
        }
        process.exit(1);
    }

    console.log(`Вход выполнен (тип: ${data.type}).`);
    return data.token;
}

async function importAccounts(token) {
    console.log(`Импортируем ${accountsToImport.length} счетов в ${API_BASE}/ledger/int/v1/accounts ...`);

    for (const account of accountsToImport) {
        try {
            const response = await fetch(`${API_BASE}/ledger/int/v1/accounts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify(account),
            });

            const data = await response.json();

            if (response.ok) {
                console.log(`[OK] ${account.code} — ${account.name} (${data.account?.public_id ?? data.public_id ?? ''})`);
            } else {
                console.error(`[ОШИБКА] ${account.code} — ${account.name}:`, data.message ?? data.errors ?? data);
            }
        } catch (err) {
            console.error(`[СЕТЕВАЯ ОШИБКА] ${account.code}:`, err.message);
        }
    }

    console.log('Импорт завершён.');
}

const token = await login();
await importAccounts(token);
