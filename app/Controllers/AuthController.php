<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Login and logout controller.
 */
final class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin(Request $request): Response
    {
        return $this->render('auth/login', ['errors' => []]);
    }

    /**
     * Authenticate the submitted credentials.
     */
    public function login(Request $request): Response
    {
        $this->requireCsrf($request);

        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $validator = new Validator();
        $validator->email('email', $email, 'Please enter a valid email address.')
            ->required('password', $password, 'Password is required.');

        if (!$validator->passes()) {
            return $this->render('auth/login', ['errors' => $validator->errors(), 'email' => $email]);
        }

        if (!$this->app->auth()->attempt($email, $password)) {
            return $this->render('auth/login', [
                'errors' => ['login' => 'The credentials are not valid.'],
                'email' => $email,
            ]);
        }

        $intended = (string) $this->app->session()->get('intended_url', '/dashboard');
        $this->app->session()->forget('intended_url');
        return $this->redirect($intended);
    }

    /**
     * Log out the current user.
     */
    public function logout(Request $request): Response
    {
        $this->requireCsrf($request);
        $this->app->auth()->logout();
        return $this->redirect('/');
    }
}
