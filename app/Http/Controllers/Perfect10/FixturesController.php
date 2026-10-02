<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\FlavourContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixturesController extends Controller
{
    public function __construct(
        private FlavourContextService $flavourContext,
    ) {}

    public function index(Request $request): Response
    {
        $flavours = $this->flavourContext->availableFlavours();

        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );

        $requestedCode = trim(
            $request->string('flavour')->toString()
        );

        $selected = $flavours->firstWhere(
            'flavour_code',
            $requestedCode
        );

        $selected ??=
            $flavours->firstWhere('is_default', true)
            ?? $flavours->first();

        return Inertia::render('perfect10/fixtures', [
            'flavours' => $flavours,
            'flavour' => $selected,
        ]);
    }
}
