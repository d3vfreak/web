<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2020 Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Web\Http\Controllers\Profile;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Services\Settings\Profile;
use Seat\Web\Http\Controllers\Controller;
use Seat\Web\Http\Validation\EmailUpdate;
use Seat\Web\Http\Validation\ProfileSettings;
use Seat\Web\Http\Validation\Sharelink;
use Seat\Web\Models\User;
use Seat\Web\Models\UserSharelink;

/**
 * Class ProfileController.
 * @package Seat\Web\Http\Controllers\Profile
 */
class ProfileController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getView()
    {

        $user = User::with('refresh_tokens', 'characters', 'roles.permissions')
            ->find(auth()->user()->id);

        $history = auth()->user()->login_history->take(50)->sortByDesc('created_at');

        // Settings value possibilities
        $characters = auth()->user()->characters;

        // available languages
        $languages = config('web.locale.languages');

        // available options
        $skins = Profile::$options['skins'];
        $sidebar = Profile::$options['sidebar'];
        $thousand = Profile::$options['thousand_seperator'];
        $decimal = Profile::$options['decimal_seperator'];

        return view('web::profile.view',
            compact('user', 'history', 'characters', 'skins', 'languages',
                'sidebar', 'thousand', 'decimal'));
    }

    /**
     * @param int $user_id
     * @param int $character_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getCharacterScopes(int $user_id, int $character_id)
    {
        $token = RefreshToken::where('character_id', $character_id)
            ->where('user_id', $user_id)
            ->first();

        return view('web::profile.modals.scopes.content', ['scopes' => $token->scopes]);
    }

    /**
     * @param \Seat\Web\Http\Validation\ProfileSettings $request
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function postUpdateUserSettings(ProfileSettings $request)
    {

        // Update the rest of the settings
        Profile::set('main_character_id', $request->main_character_id);
        Profile::set('skin', $request->skin);
        Profile::set('language', $request->language);
        Profile::set('sidebar', $request->sidebar);
        Profile::set('mail_threads', $request->mail_threads);

        Profile::set('thousand_seperator', $request->thousand_seperator);
        Profile::set('decimal_seperator', $request->decimal_seperator);
        Profile::set('reprocessing_yield', $request->reprocessing_yield);

        Profile::set('email_notifications', $request->email_notifications);

        return redirect()->back()
            ->with('success', 'Profile settings updated!');
    }

    /**
     * @param \Seat\Web\Http\Validation\EmailUpdate $request
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function postUpdateEmail(EmailUpdate $request)
    {

        Profile::set('email_address', $request->new_email);

        return redirect()->back()
            ->with('success', 'Email updated!');
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postChangeCharacter(Request $request)
    {
        $request->validate([
            'character_id' => 'integer|exists:character_infos,character_id|required',
        ]);

        $character_id = $request->character_id;

        $requested_character = auth()->user()->characters->filter(function ($character) use ($character_id) {
            return $character->character_id == $character_id;
        })->first();

        // Prevent login to arbitrary characters.
        if (! $requested_character) {

            // Log this attempt
            event('security.log', ['Character change denied ', 'authentication']);
            abort(404);
        }

        // Find the new user to login as.
        auth()->user()->fill([
            'main_character_id' => $requested_character->character_id,
            'name'              => $requested_character->name,
        ])->save();

        // Log the character change login event.
        event('security.log', [
            'Main character change to ' . $requested_character->name . ' from ' . auth()->user()->name,
            'authentication',
        ]);

        auth()->user()->fresh();

        return redirect()->back();
    }

    /**
     * @param \Seat\Web\Http\Validation\Sharelink $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postUpdateSharelink(Sharelink $request)
    {
        // Validate our character id is valid for the submitting user
        if ((int) $request->input('user_sharelink_character_id') !== 0) {
            $requested_character = auth()->user()->characters->contains((int) $request->input('user_sharelink_character_id'));
            if (! $requested_character) {
                event('security.log', [
                    auth()->user()->name . ' tried to create invalid sharelink.', 'sharelink',
                ]);

                abort(403);
            }
        }

        // Generate a random 32 character string to use as the sharelink.
        // Concat both user_id and character_id as determinant and encode the result.
        $token = base64_encode(
            sprintf('%s.%s.%s',
                auth()->user()->id, Str::random(32), (int) $request->input('user_sharelink_character_id')));

        UserSharelink::updateOrCreate(
            [
                'token' => $token,
            ],
            [
                'user_id' => auth()->user()->id,
                'character_id' => (int) $request->input('user_sharelink_character_id'),
                'expires_on' => now()->addDays((int) $request->input('user_sharelink_expiry')),
            ]
        );

        // Log the new sharelink creation
        event('security.log', [
            'New share link created for user ' . auth()->user()->name, 'sharelink',
        ]);

        return redirect()->back()
            ->with('success', 'A new sharing link has been generated!');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteRemoveSharelink(Request $request)
    {
        // Check user is authorised to remove the sharelink.
        $token = UserSharelink::find($request->input('token'));

        if(! $token || $token->user_id != auth()->user()->id) {

            event('security.log', [
                auth()->user()->name . ' tried to remove a sharelink that did not belong to them.', 'sharelink',
            ]);

            return redirect()->back()
                ->with('error', 'Invalid token!');
        }

        // Delete the model.
        $token->delete();

        return redirect()->back()
            ->with('success', 'Sharelink has been removed!');
    }
}
