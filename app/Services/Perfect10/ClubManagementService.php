<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\ClubInvite;
use App\Models\Perfect10\ClubMembership;
use App\Models\Perfect10\ClubProfile;
use App\Models\Perfect10\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClubManagementService
{
    public function changeMemberRole(
        ClubProfile $club,
        string $currentProfileId,
        string $memberProfileId,
        string $newRole
    ): void {
        $currentMembership = ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $currentProfileId)
            ->first();

        if ($currentMembership?->club_role !== 'owner') {
            throw ValidationException::withMessages([
                'role' => 'Only the owner can change roles.',
            ]);
        }

        if ($memberProfileId === $currentProfileId) {
            throw ValidationException::withMessages([
                'role' => 'Owner role cannot be changed here.',
            ]);
        }

        $targetMembership = ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $memberProfileId)
            ->first();

        if ($targetMembership === null) {
            throw ValidationException::withMessages([
                'member' => 'Member not found.',
            ]);
        }

        ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $memberProfileId)
            ->update([
                'club_role' => $newRole,
            ]);
    }

    public function sendInvite(
        ClubProfile $club,
        string $currentProfileId,
        string $username
    ): string {
        $currentMembership = ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $currentProfileId)
            ->first();

        if (
            $currentMembership === null ||
            ! in_array(
                $currentMembership->club_role,
                ['owner', 'captain'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'username' => 'Only club owners or captains can send invites.',
            ]);
        }

        $recipient = UserProfile::query()
            ->whereRaw(
                'lower(username::text) = lower(?)',
                [trim($username)]
            )
            ->first();

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'username' => 'User not found.',
            ]);
        }

        $recipientProfileId = $recipient->profile_id;

        if ($recipientProfileId === $currentProfileId) {
            throw ValidationException::withMessages([
                'username' => 'You cannot invite yourself.',
            ]);
        }

        $alreadyMember = ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $recipientProfileId)
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'username' => 'User is already a member of this club.',
            ]);
        }

        $pendingInviteExists = ClubInvite::query()
            ->where('club_id', $club->profile_id)
            ->where('recipient_profile_id', $recipientProfileId)
            ->where('invite_status', 'pending')
            ->exists();

        if ($pendingInviteExists) {
            throw ValidationException::withMessages([
                'username' => 'User already has a pending invite.',
            ]);
        }

        $inviteId = (string) Str::uuid();

        ClubInvite::query()->create([
            'invite_id' => $inviteId,
            'club_id' => $club->profile_id,
            'sender_profile_id' => $currentProfileId,
            'recipient_profile_id' => $recipientProfileId,
            'invite_status' => 'pending',
        ]);

        return $inviteId;
    }

    public function acceptInvite(
        string $currentProfileId,
        string $inviteId
    ): void {
        DB::transaction(function () use (
            $currentProfileId,
            $inviteId
        ) {
            $invite = ClubInvite::query()
                ->where('invite_id', $inviteId)
                ->where('recipient_profile_id', $currentProfileId)
                ->where('invite_status', 'pending')
                ->first();

            if ($invite === null) {
                throw ValidationException::withMessages([
                    'invite' => 'Invite not found.',
                ]);
            }

            ClubInvite::query()
                ->where('invite_id', $inviteId)
                ->update([
                    'invite_status' => 'accepted',
                ]);

            ClubMembership::query()->create([
                'club_id' => $invite->club_id,
                'profile_id' => $currentProfileId,
                'club_role' => 'member',
            ]);
        });
    }

    public function declineInvite(
        string $currentProfileId,
        string $inviteId
    ): void {
        $invite = ClubInvite::query()
            ->where('invite_id', $inviteId)
            ->where('recipient_profile_id', $currentProfileId)
            ->where('invite_status', 'pending')
            ->first();

        if ($invite === null) {
            throw ValidationException::withMessages([
                'invite' => 'Invite not found.',
            ]);
        }

        ClubInvite::query()
            ->where('invite_id', $inviteId)
            ->update([
                'invite_status' => 'declined',
            ]);
    }

    public function requestOwnershipTransfer(
        ClubProfile $club,
        string $currentProfileId,
        string $targetProfileId
    ): void {
        $currentMembership = ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $currentProfileId)
            ->first();
    
        if ($currentMembership === null) {
            throw ValidationException::withMessages([
                'ownership' => 'You are not a member of this club.',
            ]);
        }
    
        if ($currentMembership->club_role !== 'owner') {
            throw ValidationException::withMessages([
                'ownership' => 'Only the owner can request an ownership transfer.',
            ]);
        }
    
        if ($targetProfileId === $currentProfileId) {
            throw ValidationException::withMessages([
                'ownership' => 'You are already the owner.',
            ]);
        }
    
        $targetMembership = ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $targetProfileId)
            ->first();
    
        if ($targetMembership === null) {
            throw ValidationException::withMessages([
                'ownership' => 'New owner must already be a club member.',
            ]);
        }
    
        $pendingRequestExists = ClubOwnershipTransferRequest::query()
            ->where('club_id', $club->profile_id)
            ->where('status', 'pending')
            ->exists();
    
        if ($pendingRequestExists) {
            throw ValidationException::withMessages([
                'ownership' => 'This club already has a pending ownership transfer.',
            ]);
        }
    
        ClubOwnershipTransferRequest::query()->create([
            'club_id' => $club->profile_id,
            'owner_profile_id' => $currentProfileId,
            'target_profile_id' => $targetProfileId,
            'status' => 'pending',
        ]);
    }
    
    public function acceptOwnershipTransfer(
        string $currentProfileId,
        string $requestId
    ): void {
        DB::transaction(function () use (
            $currentProfileId,
            $requestId
        ) {
            $request = ClubOwnershipTransferRequest::query()
                ->where('request_id', $requestId)
                ->where('target_profile_id', $currentProfileId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();
        
            if ($request === null) {
                throw ValidationException::withMessages([
                    'ownership' => 'Ownership transfer request not found or expired.',
                ]);
            }
        
            $ownerMembership = ClubMembership::query()
                ->where('club_id', $request->club_id)
                ->where('profile_id', $request->owner_profile_id)
                ->first();
        
            if ($ownerMembership?->club_role !== 'owner') {
                throw ValidationException::withMessages([
                    'ownership' => 'The ownership transfer is no longer valid.',
                ]);
            }
        
            $targetMembership = ClubMembership::query()
                ->where('club_id', $request->club_id)
                ->where('profile_id', $currentProfileId)
                ->first();
        
            if ($targetMembership === null) {
                throw ValidationException::withMessages([
                    'ownership' => 'You are no longer a member of this club.',
                ]);
            }
        
            ClubMembership::query()
                ->where('club_id', $request->club_id)
                ->where('profile_id', $request->owner_profile_id)
                ->update([
                    'club_role' => 'member',
                ]);
            
            ClubMembership::query()
                ->where('club_id', $request->club_id)
                ->where('profile_id', $currentProfileId)
                ->update([
                    'club_role' => 'owner',
                ]);
            
            ClubOwnershipTransferRequest::query()
                ->where('request_id', $requestId)
                ->update([
                    'status' => 'accepted',
                ]);
            
            ClubOwnershipTransferRequest::query()
                ->where('club_id', $request->club_id)
                ->where('status', 'pending')
                ->where('request_id', '<>', $requestId)
                ->update([
                    'status' => 'expired',
                ]);
        });
    }
    
    public function declineOwnershipTransfer(
        string $currentProfileId,
        string $requestId
    ): void {
        $request = ClubOwnershipTransferRequest::query()
            ->where('request_id', $requestId)
            ->where('target_profile_id', $currentProfileId)
            ->where('status', 'pending')
            ->first();
    
        if ($request === null) {
            throw ValidationException::withMessages([
                'ownership' => 'Ownership transfer request not found.',
            ]);
        }
    
        ClubOwnershipTransferRequest::query()
            ->where('request_id', $requestId)
            ->update([
                'status' => 'declined',
            ]);
    }

    public function removeMember(
        ClubProfile $club,
        string $currentProfileId,
        string $memberProfileId
    ): void {
        $currentMembership = ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $currentProfileId)
            ->first();

        if (
            $currentMembership === null ||
            ! in_array(
                $currentMembership->club_role,
                ['owner', 'captain'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'member' => 'Only the owner or captain can remove members.',
            ]);
        }

        $targetMembership = ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $memberProfileId)
            ->first();

        if ($targetMembership === null) {
            throw ValidationException::withMessages([
                'member' => 'Member not found.',
            ]);
        }

        if ($targetMembership->club_role === 'owner') {
            throw ValidationException::withMessages([
                'member' => 'The club owner cannot be removed.',
            ]);
        }

        ClubMembership::query()
            ->where('club_id', $club->profile_id)
            ->where('profile_id', $memberProfileId)
            ->delete();
    }
}