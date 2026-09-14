<?php

namespace AnyMedia\Interpresso\Tests\Feature;

use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\TranslatorPasswords;
use AnyMedia\Interpresso\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class PasswordResetConnectionTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set([
            'database.connections.translator_accounts' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'interpresso.db_connection' => 'translator_accounts',
            'interpresso.table_password_reset_tokens' => 'custom_password_reset_tokens',
        ]);
    }

    #[Test]
    public function broker_issues_and_consumes_tokens_on_the_configured_connection_and_table(): void
    {
        $translator = Translator::firstOrFail();
        $passwords = resolve(TranslatorPasswords::class);
        $token = $passwords->broker()->createToken($translator);
        $this->assertTrue(Schema::connection('translator_accounts')->hasTable('custom_password_reset_tokens'));
        $this->assertFalse(Schema::connection('testbench')->hasTable('custom_password_reset_tokens'));
        $this->assertTrue(Hash::check($token, DB::connection('translator_accounts')->table('custom_password_reset_tokens')->value('token')));
        $this->assertTrue($passwords->reset([
            'email' => $translator->email, 'token' => $token, 'password' => 'chosen-password', 'password_confirmation' => 'chosen-password',
        ]));
        $this->assertSame(0, DB::connection('translator_accounts')->table('custom_password_reset_tokens')->count());
        $this->assertTrue(Hash::check('chosen-password', $translator->fresh()->password));
    }
}
