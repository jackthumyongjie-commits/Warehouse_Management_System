<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = getDBConnection();
$errors = [];
$action = $_GET['action'] ?? 'list';
$editUser = null;
$currentUser = current_user();

// Process POST requests (CSRF Protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // 1. Delete User Action
    if ($postAction === 'delete') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        if ($targetUserId === (int)$currentUser['id']) {
            set_flash_message('danger', 'Security Restriction: You cannot delete your own currently logged-in account.');
        } else {
            // Check if user exists
            $stmt = $pdo->prepare('SELECT full_name FROM users WHERE id = :id');
            $stmt->execute(['id' => $targetUserId]);
            $u = $stmt->fetch();

            if ($u) {
                try {
                    $delStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $delStmt->execute(['id' => $targetUserId]);
                    set_flash_message('success', "User account '{$u['full_name']}' deleted successfully.");
                } catch (PDOException $e) {
                    error_log('Delete User Error: ' . $e->getMessage());
                    set_flash_message('danger', 'Cannot delete user because they have recorded stock movement history. Deactivate the user instead.');
                }
            }
        }
        redirect('admin/users.php');
    }

    // 2. Add / Edit User Form Actions
    if (isset($_POST['action_add']) || isset($_POST['action_edit'])) {
        $action = isset($_POST['action_edit']) ? 'edit' : 'add';
        $userId   = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $role     = trim($_POST['role'] ?? 'user');
        $password = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validations
        if (empty($fullName)) $errors[] = 'Full name is required.';
        if (empty($username)) $errors[] = 'Username is required.';
        if (!in_array($role, ['admin', 'user'], true)) $errors[] = 'Invalid user role selected.';

        // Username uniqueness check
        if (!empty($username)) {
            if (isset($_POST['action_edit']) && $userId > 0) {
                $uniqStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username AND id != :id');
                $uniqStmt->execute(['username' => $username, 'id' => $userId]);
            } else {
                $uniqStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
                $uniqStmt->execute(['username' => $username]);
            }
            if ((int)$uniqStmt->fetchColumn() > 0) {
                $errors[] = "Username '{$username}' is already taken.";
            }
        }

        // Password validation
        if (isset($_POST['action_add'])) {
            if (empty($password)) {
                $errors[] = 'Password is required for new user accounts.';
            } elseif (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters long for security.';
            }
        } elseif (!empty($password) && strlen($password) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        }

        if (empty($errors)) {
            if (isset($_POST['action_add'])) {
                try {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $insStmt = $pdo->prepare('
                        INSERT INTO users (full_name, username, password_hash, role, is_active)
                        VALUES (:full_name, :username, :password_hash, :role, :is_active)
                    ');
                    $insStmt->execute([
                        'full_name'     => $fullName,
                        'username'      => $username,
                        'password_hash' => $passwordHash,
                        'role'          => $role,
                        'is_active'     => $isActive
                    ]);

                    set_flash_message('success', "New account for '{$fullName}' created successfully.");
                    redirect('admin/users.php');
                } catch (PDOException $e) {
                    error_log('Add User Error: ' . $e->getMessage());
                    $errors[] = 'Database error creating user account.';
                }
            } elseif (isset($_POST['action_edit']) && $userId > 0) {
                try {
                    if (!empty($password)) {
                        // Update password along with user details
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $upStmt = $pdo->prepare('
                            UPDATE users 
                            SET full_name = :full_name, username = :username, password_hash = :password_hash, role = :role, is_active = :is_active
                            WHERE id = :id
                        ');
                        $upStmt->execute([
                            'full_name'     => $fullName,
                            'username'      => $username,
                            'password_hash' => $passwordHash,
                            'role'          => $role,
                            'is_active'     => $isActive,
                            'id'            => $userId
                        ]);
                    } else {
                        // Update details without touching password
                        $upStmt = $pdo->prepare('
                            UPDATE users 
                            SET full_name = :full_name, username = :username, role = :role, is_active = :is_active
                            WHERE id = :id
                        ');
                        $upStmt->execute([
                            'full_name' => $fullName,
                            'username'  => $username,
                            'role'      => $role,
                            'is_active' => $isActive,
                            'id'        => $userId
                        ]);
                    }

                    if ($userId === (int)$currentUser['id']) {
                        $_SESSION['user_fullname'] = $fullName;
                        $_SESSION['user_username'] = $username;
                        $_SESSION['user_role'] = $role;
                    }

                    set_flash_message('success', "User account '{$fullName}' updated successfully.");
                    redirect($role === 'admin' || $userId !== (int)$currentUser['id'] ? 'admin/users.php' : 'user/dashboard.php');
                } catch (PDOException $e) {
                    error_log('Edit User Error: ' . $e->getMessage());
                    $errors[] = 'Database error updating user account.';
                }
            }
        }
    }
}

// Handle fetching user for edit mode
if ($action === 'edit') {
    $editId = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, full_name, username, role, is_active FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editUser = $stmt->fetch();
    if (!$editUser && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        set_flash_message('danger', 'User not found.');
        redirect('admin/users.php');
    }
}

// Fetch all system users
$usersStmt = $pdo->query('SELECT id, full_name, username, role, is_active, created_at FROM users ORDER BY id ASC');
$users = $usersStmt->fetchAll();

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">User Account Management</h1>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <a href="<?= url('admin/users.php?action=add') ?>" class="btn btn-primary">+ Create New User</a>
    <?php else: ?>
        <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary">&larr; Back to Users List</a>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin-left: 1.25rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- Add / Edit User Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'edit' ? 'Edit User Account' : 'Create New System Account' ?></h2>
        </div>
        <div class="card-body">
            <form action="<?= url('admin/users.php') ?>" method="POST">
                <?= csrf_field() ?>
                <?php if ($action === 'edit' && $editUser): ?>
                    <input type="hidden" name="action_edit" value="1">
                    <input type="hidden" name="user_id" value="<?= e((string)$editUser['id']) ?>">
                <?php else: ?>
                    <input type="hidden" name="action_add" value="1">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            class="form-control" 
                            value="<?= e($editUser ? $editUser['full_name'] : ($_POST['full_name'] ?? '')) ?>" 
                            placeholder="e.g. John Doe" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            value="<?= e($editUser ? $editUser['username'] : ($_POST['username'] ?? '')) ?>" 
                            placeholder="e.g. jdoe" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="role" class="form-label">System Role <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-control" required>
                            <option value="user" <?= ($editUser ? $editUser['role'] : ($_POST['role'] ?? '')) === 'user' ? 'selected' : '' ?>>User (Read-only)</option>
                            <option value="admin" <?= ($editUser ? $editUser['role'] : ($_POST['role'] ?? '')) === 'admin' ? 'selected' : '' ?>>Administrator (Full Control)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            Password <?= $action === 'edit' ? '<span class="text-muted">(Leave blank to keep existing password)</span>' : '<span class="text-danger">*</span>' ?>
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="<?= $action === 'edit' ? 'Enter new password if changing' : 'At least 6 characters' ?>"
                            <?= $action === 'add' ? 'required' : '' ?>
                        >
                    </div>

                    <div class="form-group" style="justify-content: flex-end;">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1" 
                                <?= ($editUser ? (int)$editUser['is_active'] === 1 : true) ? 'checked' : '' ?>
                            >
                            <span>Account Active</span>
                        </label>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary">
                        <?= $action === 'edit' ? 'Update User' : 'Create User' ?>
                    </button>
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>

    <!-- Users List Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">System User Accounts</h2>
            <span class="badge badge-info"><?= count($users) ?> Accounts</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= e((string)$u['id']) ?></td>
                                <td><strong><?= e($u['full_name']) ?></strong></td>
                                <td><code><?= e($u['username']) ?></code></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge badge-primary" style="background-color: var(--primary-light); color: var(--primary-color);">ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">USER</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_datetime($u['created_at']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= url('admin/users.php?action=edit&id=' . $u['id']) ?>" class="btn btn-primary btn-sm" title="Edit / Reset Password">
                                            ✏️ Edit
                                        </a>
                                        <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                            <form action="<?= url('admin/users.php') ?>" method="POST" style="display: inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= e((string)$u['id']) ?>">
                                                <button 
                                                    type="submit" 
                                                    class="btn btn-danger btn-sm js-confirm-delete"
                                                    data-name="<?= e($u['full_name']) ?>"
                                                    title="Delete Account"
                                                >
                                                    🗑️ Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" title="Current session account">You</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
