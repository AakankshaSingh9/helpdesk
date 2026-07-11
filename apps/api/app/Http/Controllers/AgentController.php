<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Assignable staff for the ticket-assignment picker.
 *
 * Authenticated (auth:sanctum) and available to both roles — an agent needs the
 * roster to hand a ticket off. Soft-deleted users are excluded by the model's
 * SoftDeletes scope. Read-only; user management proper lives behind the
 * admin-only UserController.
 */
class AgentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $agents = User::query()
            ->orderBy('name')
            ->get();

        return UserResource::collection($agents);
    }
}
