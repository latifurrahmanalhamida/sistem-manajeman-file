<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogSuccessfulLogout
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
     * @param  \Illuminate\Auth\Events\Logout  $event
     * @return void
     */
    public function handle(Logout $event): void
    {
        // Pastikan ada user yang sedang logout
        if ($event->user) {
            DB::table('login_histories')->insert([
                'user_id' => $event->user->id,
                'action' => 'logout',
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->header('User-Agent'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}