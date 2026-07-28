<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MailTemplate;

class MailTemplateController extends Controller
{
    public function index()
    {
        $mailtemplates = MailTemplate::all();
        return view('admin.mailtemplates.index', compact('mailtemplates'));
    }
    public function create()
    {
        $mailtemplate = new MailTemplate();
        return view('admin.mailtemplates.create', compact('mailtemplate'));
    }
    public function store(Request $request)
    {
        $mailtemplate = new MailTemplate();
        $mailtemplate->name = $request->name;
        $mailtemplate->subject = $request->subject;
        $mailtemplate->body = $request->body;
        $mailtemplate->save();
        return redirect()->route('admin.mailtemplates')->with('success', 'Mail template created successfully');
    }
    public function edit($id)
    {
        $mailtemplate = MailTemplate::find($id);
        return view('admin.mailtemplates.edit', compact('mailtemplate'));
    }
    public function update(Request $request, $id)
    {
        $mailtemplate = MailTemplate::find($id);
        $mailtemplate->name = $request->name;
        $mailtemplate->subject = $request->subject;
        $mailtemplate->body = $request->body;
        $mailtemplate->save();
        return redirect()->route('admin.mailtemplates')->with('success', 'Mail template updated successfully');
    }
    
    public function destroy($id)
    {
        $mailtemplate = MailTemplate::find($id);
        $mailtemplate->delete();
        return redirect()->route('admin.mailtemplates')->with('success', 'Mail template deleted successfully');
    }
}
