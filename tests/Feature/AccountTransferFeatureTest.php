<?php

namespace Tests\Feature;

use App\Models\AccountTransfer;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTransferFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_transfer_within_same_organization_updates_balances_only(): void
    {
        $organization = $this->createApprovedOrganization();

        $source = BankAccount::create([
            'user_id' => $organization->id,
            'name' => 'Operasional',
            'bank_name' => 'Bank A',
            'account_number' => '111',
            'balance' => 1000,
            'is_default' => true,
        ]);

        $receiver = BankAccount::create([
            'user_id' => $organization->id,
            'name' => 'Penampung',
            'bank_name' => 'Bank A',
            'account_number' => '222',
            'balance' => 100,
            'is_default' => false,
        ]);

        $this->actingAs($organization)
            ->post(route('transfers.direct.store'), [
                'sender_bank_account_id' => $source->id,
                'receiver_user_id' => $organization->id,
                'receiver_bank_account_id' => $receiver->id,
                'amount' => 250,
                'transfer_date' => now()->toDateString(),
                'note' => 'Pindah dana internal',
            ])
            ->assertRedirect(route('transfers.index'));

        $source->refresh();
        $receiver->refresh();

        $this->assertSame('750.00', number_format((float) $source->balance, 2, '.', ''));
        $this->assertSame('350.00', number_format((float) $receiver->balance, 2, '.', ''));
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('account_transfers', [
            'kind' => AccountTransfer::KIND_DIRECT_TRANSFER,
            'status' => AccountTransfer::STATUS_COMPLETED,
            'sender_user_id' => $organization->id,
            'receiver_user_id' => $organization->id,
        ]);
    }

    public function test_direct_transfer_between_organizations_creates_cross_transactions(): void
    {
        $senderOrganization = $this->createApprovedOrganization();
        $receiverOrganization = $this->createApprovedOrganization();

        $source = BankAccount::create([
            'user_id' => $senderOrganization->id,
            'name' => 'Kas Pengirim',
            'balance' => 1000,
        ]);

        $target = BankAccount::create([
            'user_id' => $receiverOrganization->id,
            'name' => 'Kas Penerima',
            'balance' => 200,
        ]);

        $this->actingAs($senderOrganization)
            ->post(route('transfers.direct.store'), [
                'sender_bank_account_id' => $source->id,
                'receiver_user_id' => $receiverOrganization->id,
                'receiver_bank_account_id' => $target->id,
                'amount' => 300,
                'transfer_date' => now()->toDateString(),
                'note' => 'Transfer antar organisasi',
            ])
            ->assertRedirect(route('transfers.index'));

        $source->refresh();
        $target->refresh();

        $this->assertSame('700.00', number_format((float) $source->balance, 2, '.', ''));
        $this->assertSame('500.00', number_format((float) $target->balance, 2, '.', ''));
        $this->assertSame(2, Transaction::count());
        $this->assertSame(1, Transaction::where('user_id', $senderOrganization->id)->where('type', 'expense')->count());
        $this->assertSame(1, Transaction::where('user_id', $receiverOrganization->id)->where('type', 'income')->count());
        $this->assertSame(
            2,
            Transaction::query()
                ->where('note', 'like', '%[TRANSFER]%')
                ->count()
        );
    }

    public function test_payment_request_can_be_created_and_paid_by_target_organization(): void
    {
        $requesterOrganization = $this->createApprovedOrganization();
        $payerOrganization = $this->createApprovedOrganization();

        $requesterReceiver = BankAccount::create([
            'user_id' => $requesterOrganization->id,
            'name' => 'Penampung Requester',
            'balance' => 100,
        ]);

        $payerSource = BankAccount::create([
            'user_id' => $payerOrganization->id,
            'name' => 'Kas Payer',
            'balance' => 900,
        ]);

        $this->actingAs($requesterOrganization)
            ->post(route('transfers.requests.store'), [
                'payer_user_id' => $payerOrganization->id,
                'receiver_bank_account_id' => $requesterReceiver->id,
                'amount' => 400,
                'transfer_date' => now()->toDateString(),
                'note' => 'Tagihan kegiatan bersama',
            ])
            ->assertRedirect(route('transfers.index'));

        $transfer = AccountTransfer::query()->latest('id')->firstOrFail();
        $this->assertSame(AccountTransfer::STATUS_PENDING, $transfer->status);
        $this->assertSame(AccountTransfer::KIND_PAYMENT_REQUEST, $transfer->kind);
        $this->assertSame($payerOrganization->id, (int) $transfer->sender_user_id);
        $this->assertSame($requesterOrganization->id, (int) $transfer->receiver_user_id);

        $this->actingAs($payerOrganization)
            ->post(route('transfers.requests.pay', $transfer), [
                'sender_bank_account_id' => $payerSource->id,
                'transfer_date' => now()->toDateString(),
                'note' => 'Pembayaran disetujui',
            ])
            ->assertRedirect(route('transfers.index'));

        $transfer->refresh();
        $payerSource->refresh();
        $requesterReceiver->refresh();

        $this->assertSame(AccountTransfer::STATUS_COMPLETED, $transfer->status);
        $this->assertSame($payerSource->id, (int) $transfer->sender_bank_account_id);
        $this->assertSame('500.00', number_format((float) $payerSource->balance, 2, '.', ''));
        $this->assertSame('500.00', number_format((float) $requesterReceiver->balance, 2, '.', ''));
        $this->assertSame(2, Transaction::count());
    }

    private function createApprovedOrganization(): User
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'is_platform_admin' => false,
            'permissions' => null,
            'account_status' => User::STATUS_APPROVED,
            'approved_at' => now(),
            'data_owner_user_id' => null,
        ]);

        $user->update(['data_owner_user_id' => $user->id]);

        return $user->fresh();
    }
}
