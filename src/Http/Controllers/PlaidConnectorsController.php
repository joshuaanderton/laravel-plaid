<?php

namespace Ja\LaravelPlaid\Http\Controllers;

use Exception;
use App\Models\PlaidConnector;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Ja\LaravelPlaid\Actions\ImportAccounts;

class PlaidConnectorsController
{
    private function createOrUpdateLink(?string $accessToken = null)
    {
        $user = Auth::user();

        $data = [
            'client_id' => env('PLAID_CLIENT_ID'),
            'secret' => env('PLAID_SECRET_KEY'),
            'client_name' => env('PLAID_CLIENT_NAME', env('APP_NAME')),
            'user' => [
                'client_user_id' => (string) $user->id,
                'legal_name' => $user->name,
                'email_address' => $user->email,
                'email_address_verified_time' => (string) ($user->email_verified_at ?: now())->toISOString(),
                'phone_number' => $user->phone,
            ],
            'country_codes' => ['CA'],
            'language' => 'en',
            'redirect_uri' => Str::replaceFirst('http://', 'https://', route('plaid_connectors.create')),
            'webhook' => route('plaid_connectors.webhooks.store'),
            'products' => ['transactions'],
        ];

        if ($accessToken) {
            $data['access_token'] = $accessToken;
        }

        $apiHost = (new PlaidConnector)->plaidApiHost();

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post("{$apiHost}/link/token/create", $data);

        if ($response->failed()) {
            throw new Exception($response['error_message']);
        }

        return $response['link_token'];
    }

    public function updateName(Request $request, PlaidConnector $plaid_connector)
    {
        $team = $request->user()->currentTeam;
        abort_if($team->id !== $plaid_connector->team_id, 403);

        $request->validate(['name' => 'required|string|max:255']);

        $plaid_connector->update($request->only(['name']));

        session()->flash('flash.banner', __('Connection name updated successfully.'));

        return redirect()->back();
    }

    public function update(Request $request, PlaidConnector $plaid_connector)
    {
        $team = $request->user()->currentTeam;

        abort_unless($team->id === $plaid_connector->team_id, 403);

        $request->validate([
            'public_token' => 'required|string',
            'name' => 'required|string',
            'institution_name' => 'required|string',
            'plaid_institution_id' => 'required|string',
        ]);

        $apiHost = $plaid_connector->plaidApiHost();

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post("{$apiHost}/item/public_token/exchange", [
            'client_id' => env('PLAID_CLIENT_ID'),
            'secret' => env('PLAID_SECRET_KEY'),
            'public_token' => $request->public_token,
        ]);

        $data = array_merge(
            [
                'access_token' => $response['access_token'],
                'plaid_item_id' => $response['item_id'],
                'requires_reconnect' => false,
            ],
            $request->only([
                'name',
                'institution_name',
                'plaid_institution_id',
            ])
        );

        $plaid_connector->update($data);

        ImportAccounts::dispatch($plaid_connector);

        session()->flash('flash.banner', __('Account connection updated successfully.'));

        return redirect()->route('plaid_connectors.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'public_token' => 'required|string',
            'name' => 'required|string',
            'institution_name' => 'required|string',
            'plaid_institution_id' => 'required|string',
            'plaid_link_token_id' => 'required|string',
        ]);

        $apiHost = (new PlaidConnector)->plaidApiHost();

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post("{$apiHost}/item/public_token/exchange", [
            'client_id' => env('PLAID_CLIENT_ID'),
            'secret' => env('PLAID_SECRET_KEY'),
            'public_token' => $request->public_token,
        ]);

        $team = $request->user()->currentTeam;

        $data = array_merge(
            [
                'access_token' => $response['access_token'],
                'plaid_item_id' => $response['item_id'],
            ],
            $request->only([
                'name',
                'institution_name',
                'plaid_institution_id',
                'plaid_link_token_id',
            ])
        );

        $plaidConnector = $team->plaidConnectors()->create($data);

        ImportAccounts::dispatch($plaidConnector);

        session()->flash('flash.banner', __('Account connected successfully.'));

        return redirect()->route('plaid_connectors.index');
    }

    public function create()
    {
        return Inertia::render('PlaidConnectors/Create', [
            'plaidLinkToken' => $this->createOrUpdateLink(),
            'storeRoute' => route('plaid_connectors.store'),
        ]);
    }

    public function edit(PlaidConnector $plaid_connector)
    {
        return Inertia::render('PlaidConnectors/Edit', [
            'plaidLinkToken' => $this->createOrUpdateLink($plaid_connector->access_token),
            'updateRoute' => route('plaid_connectors.update', $plaid_connector),
        ]);
    }

    public function index(Request $request)
    {
        return Inertia::render('PlaidConnectors/Index', [
            'plaidConnectors' => $request->user()->currentTeam->plaidConnectors
        ]);
    }
}
