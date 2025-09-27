<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogSuccessfulLogin
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * Create the event listener.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Auth\Events\Login  $event
     * @return void
     */
    public function handle(Login $event): void
    {
        DB::table('login_histories')->insert([
            'user_id' => $event->user->id,
            'action' => 'login',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->header('User-Agent'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}