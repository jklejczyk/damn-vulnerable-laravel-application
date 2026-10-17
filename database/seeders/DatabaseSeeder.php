<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Fixed accounts and data, so VULNERABILITIES.md can point at concrete records
 * ("sign in as accountant@acme.test and open GLOBEX/2026/002"). Every password is "password".
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COMPANIES = [
        'acme' => [
            'name' => 'Acme Sp. z o.o.',
            'tax_id' => '5250000001',
            'address' => "ul. Przykładowa 1\n00-001 Warszawa",
            'clients' => ['Wayne Enterprises', 'Stark Industries', 'Umbrella Corp'],
        ],
        'globex' => [
            'name' => 'Globex S.A.',
            'tax_id' => '5250000002',
            'address' => "ul. Testowa 2\n30-001 Kraków",
            'clients' => ['Initech', 'Hooli', 'Soylent Ltd'],
        ],
    ];

    /**
     * Status, days since issue, items as [description, quantity, unit price in grosze].
     */
    private const INVOICES = [
        [InvoiceStatus::Paid, 40, [['Web development', 40, 15_000], ['Hosting (annual)', 1, 120_000]]],
        [InvoiceStatus::Sent, 10, [['Consulting', 8, 25_000]]],
        [InvoiceStatus::Sent, 30, [['Design', 12, 18_000], ['Support plan', 3, 50_000]]],
        [InvoiceStatus::Draft, 1, [['Training', 2, 90_000]]],
        [InvoiceStatus::Void, 20, [['Consulting', 1, 25_000]]],
    ];

    public function run(): void
    {
        $password = Hash::make('password');

        User::factory()->admin()->create([
            'name' => 'DVLA Operator',
            'email' => 'admin@dvla.test',
            'password' => $password,
        ]);

        foreach (self::COMPANIES as $slug => $data) {
            $this->seedCompany($slug, $data, $password);
        }
    }

    /**
     * @param  array{name: string, tax_id: string, address: string, clients: list<string>}  $data
     */
    private function seedCompany(string $slug, array $data, string $password): void
    {
        $company = Company::create([
            'name' => $data['name'],
            'slug' => $slug,
            'tax_id' => $data['tax_id'],
            'address' => $data['address'],
        ]);

        $users = [];

        foreach ([Role::Owner, Role::Accountant, Role::Member] as $role) {
            $users[$role->value] = User::factory()->for($company)->create([
                'name' => ucfirst($slug).' '.$role->label(),
                'email' => $role->value.'@'.$slug.'.test',
                'password' => $password,
                'role' => $role,
            ]);
        }

        $clients = array_map(
            fn (string $name): Client => Client::factory()->for($company)->create([
                'name' => $name,
                'email' => 'billing@'.str($name)->slug().'.test',
            ]),
            $data['clients'],
        );

        $year = (int) now()->format('Y');

        foreach (self::INVOICES as $index => [$status, $daysAgo, $items]) {
            $issued = Carbon::today()->subDays($daysAgo);

            $invoice = Invoice::factory()->for($company)->create([
                'client_id' => $clients[$index % count($clients)]->id,
                'user_id' => $users[$status === InvoiceStatus::Draft ? 'member' : 'accountant']->id,
                'number' => sprintf('%s/%d/%03d', strtoupper($slug), $year, $index + 1),
                'status' => $status,
                'issue_date' => $issued,
                'due_date' => $issued->copy()->addDays($company->payment_terms_days),
                'sent_at' => $status === InvoiceStatus::Draft ? null : $issued,
            ]);

            foreach ($items as [$description, $quantity, $unitPrice]) {
                $invoice->items()->create(['description' => $description, 'quantity' => $quantity, 'unit_price' => $unitPrice]);
            }

            $invoice->recalculate();

            if ($status === InvoiceStatus::Paid) {
                Payment::factory()->forInvoice($invoice)->create([
                    'provider_reference' => 'pay_'.$slug.'_'.($index + 1),
                    'paid_at' => $issued->copy()->addDays(5),
                ]);
            }
        }
    }
}
