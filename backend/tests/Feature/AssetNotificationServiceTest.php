<?php

namespace Tests\Feature;

use App\Mail\AssetEventMail;
use App\Models\User;
use App\Services\AssetNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class AssetNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_damage_flagged_emails_every_opm_role_lookup_never_hardcoded(): void
    {
        Mail::fake();
        $opm1 = User::factory()->create(['role' => 'operations_hr_manager']);
        $opm2 = User::factory()->create(['role' => 'operations_hr_manager']);
        $staff = User::factory()->create(['role' => 'staff']);

        (new AssetNotificationService)->send('DAMAGE_FLAGGED', [
            'assetId' => 'PEY-SR-FAF-0001',
            'description' => 'Office Chair',
            'location' => 'PEPY Office',
            'flaggedBy' => $staff,
            'note' => 'Leg is broken',
        ]);

        Mail::assertSent(AssetEventMail::class, 2);
        Mail::assertSent(AssetEventMail::class, fn ($mail) => $mail->hasTo($opm1->email));
        Mail::assertSent(AssetEventMail::class, fn ($mail) => $mail->hasTo($opm2->email));
        Mail::assertNotSent(AssetEventMail::class, fn ($mail) => $mail->hasTo($staff->email));
    }

    public function test_unknown_flagged_by_role_throws_and_sends_nothing(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'operations_hr_manager']);

        $this->expectException(InvalidArgumentException::class);

        try {
            (new AssetNotificationService)->send('DAMAGE_FLAGGED', [
                'assetId' => 'PEY-SR-FAF-0001',
                'flaggedBy' => 'not_a_real_role',
            ]);
        } finally {
            Mail::assertNothingSent();
        }
    }

    public function test_missing_optional_fields_do_not_crash_the_send(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'operations_hr_manager']);

        (new AssetNotificationService)->send('DAMAGE_FLAGGED', [
            'assetId' => 'PEY-SR-FAF-0001',
        ]);

        Mail::assertSent(AssetEventMail::class, 1);
    }

    public function test_unknown_event_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AssetNotificationService)->send('NOT_A_REAL_EVENT', []);
    }

    public function test_disposal_request_ccs_finance_manager(): void
    {
        Mail::fake();
        $ed = User::factory()->create(['role' => 'executive_director']);
        $finance = User::factory()->create(['role' => 'finance_manager']);

        (new AssetNotificationService)->send('DISPOSAL_REQUEST', [
            'assetId' => 'PEY-SR-MOV-0001',
            'description' => 'Honda CRV',
        ]);

        Mail::assertSent(AssetEventMail::class, function ($mail) use ($ed, $finance) {
            return $mail->hasTo($ed->email) && $mail->hasCc($finance->email);
        });
    }

    public function test_a_recipient_with_no_email_is_skipped_without_error(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'operations_hr_manager', 'email' => '']);

        (new AssetNotificationService)->send('DAMAGE_FLAGGED', ['assetId' => 'PEY-SR-FAF-0001']);

        Mail::assertNothingSent();
    }
}
