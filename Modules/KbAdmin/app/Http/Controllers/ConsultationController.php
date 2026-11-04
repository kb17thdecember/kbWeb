<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class ConsultationController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        Contact::ensureQuotationColumns();

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

    public function respondWithQuotation(Request $request, Contact $contact): RedirectResponse
    {
        Contact::ensureQuotationColumns();

        $validated = $request->validate([
            'quotation_file' => ['nullable', 'file', 'mimes:pdf,xlsx,xls,doc,docx,zip', 'max:20480'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'processing', 'resolved'])],
        ]);

        $hasNewFile = $request->hasFile('quotation_file');
        $note = trim((string) ($validated['admin_note'] ?? ''));

        if (! $hasNewFile && $note === '' && ! $contact->quotation_path) {
            throw ValidationException::withMessages([
                'quotation_file' => 'Vui lòng đính kèm file báo giá PDF hoặc nhập nội dung phản hồi báo giá.',
            ]);
        }

        $updates = [
            'admin_note' => $note !== '' ? $note : $contact->admin_note,
            'quoted_at' => now(),
            'status' => $validated['status'] ?? 'resolved',
        ];

        if ($hasNewFile) {
            $file = $request->file('quotation_file');
            if ($contact->quotation_path) {
                Storage::disk('public')->delete($contact->quotation_path);
            }
            $updates['quotation_path'] = $file->store('quotations/responses', 'public');
            $updates['quotation_name'] = $file->getClientOriginalName();
        }

        $contact->update($updates);

        return back()->with('toast', 'Đã cập nhật và gửi phản hồi báo giá cho khách hàng.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return to_route('admin.consultations.index');
    }
}
