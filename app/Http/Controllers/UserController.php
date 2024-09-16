<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function list()
    {
        $users = User::orderBy("name")->get();

        return view("pages.users.list", compact(
            "users",
        ));
    }

    public function edit(int $id = null)
    {
        $user = $id
            ? User::find($id)
            : null;
        $roles = Role::all();

        return view("pages.users.edit", compact(
            "user",
            "roles",
        ));
    }

    public function process(Request $rq)
    {
        $form_data = $rq->except(["_token", "roles"]);
        if (!$rq->id) {
            $form_data["password"] = $rq->login;
        }

        $user = User::updateOrCreate(
            ["id" => $rq->id],
            $form_data
        );
        $user->roles()->sync($rq->roles);

        return redirect()->route("users.list")->with("success", "Dane użytkownika zmienione");
    }
}