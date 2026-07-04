<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);
        $contact = Contact::create($validated);
        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully!',
            'data'    => $contact
        ], 201);
    }
}
