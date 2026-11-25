<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // $this->authorize('gerenciar', $ecoponto) usa as Policies (app/Policies)
    use AuthorizesRequests;
}
