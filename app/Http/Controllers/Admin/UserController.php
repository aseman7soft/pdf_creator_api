<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Requests\Admin\UserPasswordRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:admin,manager,viewer'],
            'status' => ['nullable', 'in:active,inactive'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = User::query();

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $perPage = (int) ($filters['per_page'] ?? setting('general.items_per_page', 20));

        return view('users.index', [
            'users' => $query->orderBy('name')->paginate(max(5, min(100, $perPage)))->withQueryString(),
            'filters' => $filters,
            'roles' => UserRole::options(),
            'currentUser' => $request->user(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.form', [
            'user' => new User(['role' => UserRole::Viewer, 'is_active' => true]),
            'mode' => 'create',
            'roles' => UserRole::options(),
            'isSelf' => false,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOL),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'کاربر جدید ایجاد شد.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.form', [
            'user' => $user,
            'mode' => 'edit',
            'roles' => UserRole::options(),
            'isSelf' => $request->user()?->getKey() === $user->getKey(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $isSelf = $actor?->getKey() === $user->getKey();

        $role = UserRole::from((string) $data['role']);
        $isActive = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOL);

        if ($isSelf && $role !== UserRole::Admin) {
            return back()->withInput()->with('error', 'نمی‌توانید نقش مدیر کلی خود را تغییر دهید.');
        }

        if ($isSelf && ! $isActive) {
            return back()->withInput()->with('error', 'نمی‌توانید حساب کاربری خود را غیرفعال کنید.');
        }

        if ($this->wouldRemoveLastAdmin($user, $role, $isActive)) {
            return back()->withInput()->with('error', 'حداقل یک مدیر کل فعال باید در سیستم باقی بماند.');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
            'is_active' => $isActive,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'اطلاعات کاربر به‌روزرسانی شد.');
    }

    public function toggle(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $actor = request()->user();

        if ($actor?->getKey() === $user->getKey()) {
            return back()->with('error', 'نمی‌توانید حساب کاربری خود را غیرفعال یا فعال کنید.');
        }

        if ($user->is_active && $user->isAdmin() && $this->activeAdminCount() <= 1) {
            return back()->with('error', 'حداقل یک مدیر کل فعال باید در سیستم باقی بماند.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with(
            'success',
            $user->is_active ? 'کاربر فعال شد.' : 'کاربر غیرفعال شد.',
        );
    }

    public function resetPassword(UserPasswordRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->forceFill(['password' => $validated['password']])->save();

        return back()->with('success', 'رمز عبور کاربر بازنشانی شد.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $actor = request()->user();

        if ($actor?->getKey() === $user->getKey()) {
            return back()->with('error', 'نمی‌توانید حساب کاربری خود را حذف کنید.');
        }

        if ($user->isAdmin() && $user->is_active && $this->activeAdminCount() <= 1) {
            return back()->with('error', 'حداقل یک مدیر کل فعال باید در سیستم باقی بماند.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'کاربر حذف شد.');
    }

    private function wouldRemoveLastAdmin(User $target, UserRole $newRole, bool $newActive): bool
    {
        $wasActiveAdmin = $target->isAdmin() && $target->is_active;
        $staysActiveAdmin = $newRole === UserRole::Admin && $newActive;

        if (! $wasActiveAdmin || $staysActiveAdmin) {
            return false;
        }

        return $this->activeAdminCount() <= 1;
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->count();
    }
}
