<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActiveActivityResolver;
use App\Support\Activities\ActiveActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Today's Activity — a public, login-free operational monitoring board for
 * the PM team, built for a wall/TV display. It shows one card per PIC who
 * currently has an active activity (who / what / which machine / since when)
 * and lists the not-yet-started PICs as a small line of text.
 *
 * It is intentionally NOT a dashboard: no statistics, no charts, no
 * "completed today". Read-only; it changes nothing.
 *
 * {@see data()} backs the lightweight 60s auto-refresh — it returns only the
 * board data as JSON so the page can patch itself in place instead of doing
 * a full browser reload.
 */
class TodayActivityMonitorController extends Controller
{
    public function index(Request $request): View
    {
        return view('today-activity-monitor', [
            ...$this->board(),
            'isAdmin' => $request->user()?->isAdmin() ?? false,
        ]);
    }

    /**
     * Board data only — consumed by the auto-refresh poll. No history, no
     * reports, no dashboard aggregates.
     */
    public function data(): JsonResponse
    {
        $board = $this->board();

        $active = array_map(fn (array $entry) => [
            'name' => $entry['pic']->name,
            'photo' => $entry['pic']->photo_url,
            'initials' => $entry['pic']->initials(),
            'activity' => $entry['activity']->displayLabel(),
            'machine' => $entry['activity']->machineNumber,
            'location' => $entry['activity']->locationLabel(),
            'startTime' => $entry['activity']->startedAt->format('H:i'),
        ], $board['active']);

        return response()->json([
            'active' => $active,
            'notStarted' => array_map('strtoupper', $board['notStarted']),
            'activeCount' => count($active),
            'totalPics' => $board['totalPics'],
        ]);
    }

    /**
     * The single small query set Today's Activity needs: the active PIC
     * roster (a handful of columns) plus, per PIC, their current activity
     * derived by ActiveActivityResolver from already-indexed columns.
     *
     * @return array{active: list<array{pic: User, activity: ActiveActivity}>, notStarted: list<string>, totalPics: int}
     */
    private function board(): array
    {
        $resolver = app(ActiveActivityResolver::class);

        $pics = User::query()
            ->select(['id', 'name', 'avatar_path', 'oil_audit_started_at', 'oil_audit_action_started_at'])
            ->whereIn('role', [User::ROLE_PIC_WWD, User::ROLE_PIC_BUL])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $active = [];
        $notStarted = [];

        foreach ($pics as $pic) {
            $current = $resolver->currentFor($pic);

            if ($current) {
                $active[] = ['pic' => $pic, 'activity' => $current];
            } else {
                $notStarted[] = $pic->name;
            }
        }

        return [
            'active' => $active,
            'notStarted' => $notStarted,
            'totalPics' => $pics->count(),
        ];
    }
}
