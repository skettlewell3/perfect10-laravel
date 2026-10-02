<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\FlavourContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceholderPageController extends Controller
{
    public function __construct(
        private FlavourContextService $flavourContext,
    ) {}

    public function show(
        Request $request,
        string $page
    ): Response {
        $pages = [
            'leaderboards' => false,
            'clubs' => false,
            'gameweek' => true,
            'stats' => true,
        ];

        abort_unless(array_key_exists($page, $pages), 404);

        $flavours = $this->flavourContext->availableFlavours();

        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );

        $selected = $this->flavourContext
            ->selectFlavour(
                $flavours,
                $request->string('flavour')->toString()
            );

        if ($pages[$page]) {
            abort_unless(
                $selected['format_code'] === 'GWK',
                404
            );
        }

        return Inertia::render(
            'perfect10/'.$page,
            [
                'flavours' => $flavours,
                'flavour' => $selected,
            ]
        );
    }
}
