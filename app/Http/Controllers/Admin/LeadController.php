<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListingLead;
use App\Support\SearchTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Enquiries submitted through listing contact forms, across every listing.
 *
 * The owner's own inbox already exists; this is the operator's view of the
 * same table — needed because a lead is the one artefact a member cannot
 * recover if their account is suspended, and because spam is handled here.
 *
 * read_at is a timestamp, not a boolean: "when was this seen" answers support
 * questions that "was it seen" cannot. Marking read is idempotent — a second
 * click keeps the first timestamp rather than moving it.
 */
class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $unreadOnly = $request->boolean('unread');

        $leads = ListingLead::query()
            ->when($term !== '', function ($query) use ($term): void {
                $pattern = SearchTerm::containsPattern($term);
                $query->where(function ($inner) use ($pattern): void {
                    $inner->whereRaw(SearchTerm::likeExpression('listing_leads.name'), [$pattern])
                        ->orWhereRaw(SearchTerm::likeExpression('listing_leads.email'), [$pattern])
                        ->orWhereRaw(SearchTerm::likeExpression('listing_leads.message'), [$pattern]);
                });
            })
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->with('listing')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.leads.index', [
            'leads' => $leads,
            'term' => $term,
            'unreadOnly' => $unreadOnly,
        ]);
    }

    public function markRead(ListingLead $lead): RedirectResponse
    {
        if ($lead->read_at === null) {
            $lead->update(['read_at' => now()]);
        }

        return back()->with('status', 'Запитването е отбелязано като прочетено.');
    }

    public function destroy(ListingLead $lead): RedirectResponse
    {
        $lead->delete();

        return back()->with('status', 'Запитването е изтрито.');
    }
}
