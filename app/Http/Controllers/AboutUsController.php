<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Database\Schema\Builder;

class AboutUsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $facilitators = User::role('facilitators')->get();

        return view('pages.aboutus.index', compact('facilitators'));
    }
}
