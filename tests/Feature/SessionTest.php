<?php

namespace Tests\Feature;

use Inertia\Inertia;
use Spark\Facades\Route;
use Spark\Foundation\Exceptions\ValidationException;
use Spark\Http\{Header, Session};
use Tests\TestCase;

enum FlashName { case Notice; }
enum FlashKey: string { case Message = 'message'; }
enum FlashNumber: int { case Code = 42; }

final class SessionTest extends TestCase
{
    public function test_flash_calls_merge_arrays_strings_and_enum_keys(): void
    {
        Route::get('/flash', function () {
            inertia()->flash('message', 'first')->flash(['count' => 1])->flash(FlashKey::Message, 'last')
                ->flash(FlashName::Notice, true)->flash(FlashNumber::Code, 'code');
            return inertia('Flash');
        });
        $response = $this->get('/flash', self::INERTIA_HEADERS)->assertJsonPath('flash', ['message' => 'last', 'count' => 1, 'Notice' => true, 42 => 'code']);
        $this->assertArrayNotHasKey('flash', $response->json('props'));
    }

    public function test_conventional_flash_keys_are_consumed_and_override_same_named_custom_data(): void
    {
        Route::get('/flash', function () {
            inertia()->flash(['success' => 'custom', 'zero' => 0, 'false' => false]);
            Session::flash('info', 'Information');
            Session::flash('success', 'Saved');
            Session::flash('error', 'Problem');
            Session::flash('unrelated', 'Keep me');
            return inertia('Flash');
        });
        $this->get('/flash', self::INERTIA_HEADERS)->assertJsonPath('flash', ['success' => 'Saved', 'zero' => 0, 'false' => false, 'info' => 'Information', 'error' => 'Problem']);
        $this->assertNull(Session::getFlash('success'));
        $this->assertSame('Keep me', Session::getFlash('unrelated'));
    }

    public function test_malformed_custom_flash_is_ignored_and_new_flash_can_replace_it(): void
    {
        Route::get('/flash', function () {
            Session::flash('__inertia_flash', 'malformed');
            return inertia('Flash');
        });
        $this->assertArrayNotHasKey('flash', $this->get('/flash', self::INERTIA_HEADERS)->json());
        Session::flash('__inertia_flash', 'malformed');
        $this->assertSame(inertia(), inertia()->flash('message', 'new'));
        $this->assertSame(['message' => 'new'], Session::getFlash('__inertia_flash'));
    }

    public function test_flash_survives_a_write_redirect_and_is_delivered_once(): void
    {
        Route::post('/save', fn () => inertia()->flash('message', 'Saved')->redirect('/result'));
        Route::inertia('/result', 'Result');
        $this->post('/save', headers: self::INERTIA_HEADERS)->assertRedirect('/result');
        $first = $this->get('/result', self::INERTIA_HEADERS)->assertOk()->assertJsonPath('flash.message', 'Saved');
        $this->assertArrayNotHasKey('flash', $first->json('props'));
        $this->assertArrayNotHasKey('flash', $this->get('/result', self::INERTIA_HEADERS)->json());
    }

    public function test_initial_html_also_contains_flash_data(): void
    {
        Session::flash('success', 'Welcome');
        Route::inertia('/welcome', 'Welcome');
        $page = $this->pageFromHtml($this->get('/welcome')->assertOk()->content());
        $this->assertSame(['success' => 'Welcome'], $page['flash']);
    }

    public function test_version_refresh_does_not_consume_pending_flash(): void
    {
        Session::flash('__inertia_flash', ['message' => 'Keep']);
        Route::get('/version', function () {
            inertia()->version('new');
            return inertia('Version');
        });
        $this->get('/version', [...self::INERTIA_HEADERS, Header::X_INERTIA_VERSION => 'old'])->assertStatus(409);
        $this->assertSame(['message' => 'Keep'], Session::getFlash('__inertia_flash'));
    }

    public function test_preserve_fragment_survives_a_redirect(): void
    {
        Route::post('/save', fn () => inertia()->preserveFragment()->redirect('/result'));
        Route::inertia('/result', 'Result');
        $this->post('/save', headers: self::INERTIA_HEADERS)->assertRedirect('/result');
        $this->get('/result', self::INERTIA_HEADERS)->assertJsonPath('preserveFragment', true);
        $this->assertArrayNotHasKey('preserveFragment', $this->get('/result', self::INERTIA_HEADERS)->json());
    }

    public function test_preserve_fragment_is_one_time_when_rendering_directly(): void
    {
        Route::get('/first', fn () => inertia()->preserveFragment()->render('First'));
        Route::inertia('/second', 'Second');
        $this->get('/first', self::INERTIA_HEADERS)->assertJsonPath('preserveFragment', true);
        $this->assertArrayNotHasKey('preserveFragment', $this->get('/second', self::INERTIA_HEADERS)->json());
    }

    public function test_disabling_preserve_fragment_cancels_the_pending_flag(): void
    {
        Route::get('/fragment', fn () => inertia()->preserveFragment()->preserveFragment(false)->render('Fragment'));
        $this->assertArrayNotHasKey('preserveFragment', $this->get('/fragment', self::INERTIA_HEADERS)->json());
    }

    public function test_validation_errors_expose_first_messages_and_keep_empty_errors_as_an_object(): void
    {
        Session::flash('errors', ['email' => ['Invalid email', 'Second message'], 'name' => 'Required', 'empty' => []]);
        Route::inertia('/form', 'Form');
        $this->get('/form', self::INERTIA_HEADERS)->assertJsonPath('props.errors', ['email' => 'Invalid email', 'name' => 'Required', 'empty' => null]);
        $this->get('/form', self::INERTIA_HEADERS)->assertSee('"errors":{}');
    }

    public function test_error_bags_are_nested_and_always_included_in_partial_visits(): void
    {
        Session::flash('errors', ['email' => ['Invalid email']]);
        Route::inertia('/form', 'Form', ['value' => 1, 'other' => 2]);
        $this->get('/form', [...self::INERTIA_HEADERS,
            Header::X_INERTIA_ERROR_BAG => 'profile', Header::X_INERTIA_PARTIAL_COMPONENT => 'Form',
            Header::X_INERTIA_PARTIAL_DATA => 'value', Header::X_INERTIA_PARTIAL_EXCEPT => 'errors',
        ])->assertJsonPath('props', ['errors' => ['profile' => ['email' => 'Invalid email']], 'value' => 1]);
    }

    public function test_invalid_inertia_form_redirects_and_returns_session_errors(): void
    {
        Route::post('/form', fn () => throw ValidationException::withMessages(['email' => ['Email is required.']], ['email' => '']));
        Route::inertia('/form', 'Form');
        $this->post('/form', headers: [...self::INERTIA_HEADERS, Header::REFERER => 'http://localhost/form'])->assertRedirect('http://localhost/form');
        $this->get('/form', self::INERTIA_HEADERS)->assertOk()->assertJsonPath('props.errors.email', 'Email is required.');
    }

    public function test_history_flags_do_not_leak_to_the_next_request(): void
    {
        Route::get('/private', fn () => inertia()->withEncryptedHistory()->withClearedHistory()->render('Private'));
        Route::inertia('/public', 'Public');
        $this->get('/private', self::INERTIA_HEADERS)->assertJsonPath('encryptHistory', true)->assertJsonPath('clearHistory', true);
        $page = $this->get('/public', self::INERTIA_HEADERS)->json();
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertArrayNotHasKey('clearHistory', $page);
    }
}
