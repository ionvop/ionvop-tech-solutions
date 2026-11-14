<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard shell.
     */
    public function index(Request $request): View
    {
        $conversations = Conversation::query()
            ->withLastMessage()
            ->with('lastMessage.attachment')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $selected = null;

        if ($request->filled('conversation')) {
            $selected = $conversations->firstWhere('reference', $request->string('conversation')->toString());
        }

        $selected ??= $conversations->first();

        if ($selected !== null) {
            $selected->forceFill(['admin_read_at' => now()])->save();
        }

        return view('admin.dashboard', [
            'conversations' => $conversations->map(ConversationPayload::summary(...))->values(),
            'selected' => $selected === null ? null : ConversationPayload::detail($selected),
            'messages' => $selected === null
                ? collect()
                : $selected->messages()->with('attachment')->orderBy('id')->get()->map(ConversationPayload::message(...))->values(),
            'stages' => Conversation::stageOptions(),
            'contractStatuses' => Conversation::CONTRACT_STATUSES,
            'currencies' => ['PHP', 'USD'],
            'user' => $request->user(),
        ]);
    }
}
