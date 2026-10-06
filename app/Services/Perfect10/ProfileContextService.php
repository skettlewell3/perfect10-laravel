namespace App\Services\Perfect10;

use App\Models\User;
use App\Models\Perfect10\UserProfile;
use RuntimeException;

class ProfileContextService
{
    public function forUser(User $user): UserProfile
    {
        $profile = UserProfile::query()
            ->where('elsewhere_user_id', $user->id)
            ->with('profile')
            ->first();

        if ($profile === null) {
            throw new RuntimeException(
                "Elsewhere user {$user->id} has no Perfect10 profile."
            );
        }

        return $profile;
    }
}