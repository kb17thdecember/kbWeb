<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class ConsultationController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        return Inertia::render('contacts/Index', [
            'contacts' => Contact::query()
                ->orderByDesc('created_at')
                ->get(),
            ...$dashboardStats->summary(),
        ]);
    }

    public function updateStatus(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'processing', 'resolved'])],
        ]);

        $contact->update($validated);

        return to_route('admin.consultations.index');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return to_route('admin.consultations.index');
    }
}
