<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Hộp thư liên hệ: messages from the storefront contact form (/lien-he).
 * status: new (chưa đọc) → read (đã đọc) → archived (lưu trữ).
 */
class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $box = in_array($request->input('box'), ['unread', 'read', 'archived', 'all'], true) ? $request->input('box') : 'inbox';
        $search = trim((string) $request->input('search', ''));

        $messages = ContactMessage::query()
            ->when($box === 'inbox', fn ($query) => $query->whereIn('status', [ContactMessage::STATUS_NEW, ContactMessage::STATUS_READ]))
            ->when($box === 'unread', fn ($query) => $query->where('status', ContactMessage::STATUS_NEW))
            ->when($box === 'read', fn ($query) => $query->where('status', ContactMessage::STATUS_READ))
            ->when($box === 'archived', fn ($query) => $query->where('status', ContactMessage::STATUS_ARCHIVED))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    foreach (['name', 'phone', 'email', 'subject', 'message'] as $column) {
                        $inner->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            })
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/ContactMessages/Index', [
            'messages' => $messages,
            'filters' => ['box' => $box, 'search' => $search],
            'canManage' => $this->canManage($request),
            'counts' => [
                'unread' => ContactMessage::query()->where('status', ContactMessage::STATUS_NEW)->count(),
                'archived' => ContactMessage::query()->where('status', ContactMessage::STATUS_ARCHIVED)->count(),
            ],
        ]);
    }

    public function show(Request $request, ContactMessage $contactMessage)
    {
        // Opening a new message marks it read, like an email inbox.
        if ($contactMessage->status === ContactMessage::STATUS_NEW && $this->canManage($request)) {
            $contactMessage->update(['status' => ContactMessage::STATUS_READ]);
        }

        return Inertia::render('Admin/ContactMessages/Show', [
            'message' => $contactMessage,
            'canManage' => $this->canManage($request),
        ]);
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->hasRole('super-admin') || $user->checkPermissionTo('contact-messages.manage'));
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ContactMessage::STATUS_NEW, ContactMessage::STATUS_READ, ContactMessage::STATUS_ARCHIVED])],
        ]);
        $contactMessage->update($validated);

        return back()->with('success', match ($validated['status']) {
            ContactMessage::STATUS_NEW => 'Đã đánh dấu chưa đọc',
            ContactMessage::STATUS_READ => 'Đã đánh dấu đã đọc',
            default => 'Đã lưu trữ tin nhắn',
        });
    }

    public function destroy(ContactMessage $contactMessage)
    {
        $contactMessage->delete();

        return redirect()->route('admin.contact-messages.index')->with('success', 'Đã xóa tin nhắn');
    }
}
