<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require '/home/u414139450/vendor/autoload.php';
require_once 'config.php';
require_once 'functions.php';

$db = Database::getInstance();

// Server-side: optionally accept search query param
$search = $_GET['search'] ?? '';
$params = [];
$sql = "SELECT id, username, email, first_name, last_name, is_business_owner, is_banned, profile_image_url, created_at FROM users WHERE 1=1";
if (!empty($search)) {
    $sql .= " AND (username ILIKE ? OR email ILIKE ? OR first_name ILIKE ? OR last_name ILIKE ?)";
    $term = "%{$search}%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}
$sql .= " ORDER BY created_at DESC LIMIT 200";
$users = $db->fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>User Management</title>
  <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* small toggle style like your example */
    .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
    .switch input { display:none; }
    .slider { position:absolute; cursor:pointer; background:#ccc; border-radius:28px; top:0; left:0; right:0; bottom:0; transition:.4s; }
    .slider:before { content:""; position:absolute; height:20px; width:20px; left:4px; bottom:4px; background:white; transition:.4s; border-radius:50%; }
    input:checked + .slider { background:#4CAF50; }
    input:checked + .slider:before { transform: translateX(22px); }
  </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <!-- Sidebar same as your example (trimmed for brevity) -->
  <aside class="fixed top-0 left-0 z-40 w-64 h-screen bg-gray-50 dark:bg-gray-800 p-4">
    <ul class="space-y-2">
      <li><a href="/" class="block p-2">🏠 Dashboard</a></li>
      <li><a href="user-management.php" class="block p-2 font-semibold text-blue-600">👥 User Management</a></li>
      <li><a href="business-management.php" class="block p-2">🏢 Business Management</a></li>
    </ul>
  </aside>

  <div class="p-6 sm:ml-64">
    <h1 class="text-2xl font-semibold mb-6">User Management</h1>

    <form method="GET" class="mb-4 flex gap-3">
      <input type="text" name="search" placeholder="Search users by username, name or email..."
             value="<?= htmlspecialchars($search) ?>"
             class="border rounded px-4 py-2 w-1/2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Search</button>
      <button type="button" onclick="fetchUsers()" class="bg-gray-200 px-4 py-2 rounded">Refresh</button>
    </form>

    <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow">
      <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
          <tr>
            <th class="px-6 py-3">User</th>
            <th class="px-6 py-3">Email</th>
            <th class="px-6 py-3">Business Owner</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="usersTableBody">
          <?php if (empty($users)): ?>
            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found.</td></tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr class="border-t hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <img src="<?= htmlspecialchars($u['profile_image_url']!=null && str_contains($u['profile_image_url'], "://") ?  $u['profile_image_url'] : "https://mainstreet.lt/".$u['profile_image_url']) ?>"
                         alt="No image" class="w-10 h-10 rounded-full object-cover">
                    <div>
                      <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($u['username']) ?></div>
                      <div class="text-xs text-gray-500"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4"><?= htmlspecialchars($u['email']) ?></td>
                <td class="px-6 py-4"><?= $u['is_business_owner'] ? '✅' : '—' ?></td>
                <td class="px-6 py-4"><?= $u['is_banned'] ? '<span class="text-red-600 font-semibold">Banned</span>' : '<span class="text-green-600">Active</span>' ?></td>
                <td class="px-6 py-4 text-right">
                  <button onclick="openUserModal('<?= $u['id'] ?>')" class="text-green-600 hover:underline mr-2">View</button>
                  <button onclick="openEditModal('<?= $u['id'] ?>')" class="text-blue-600 hover:underline mr-2">Edit</button>
                  <button onclick="openDeleteModal('<?= $u['id'] ?>')" class="text-red-600 hover:underline mr-2">Delete</button>
                  <?php if ($u['is_banned']): ?>
                    <button onclick="unbanUser('<?= $u['id'] ?>')" class="text-yellow-600 hover:underline">Unban</button>
                  <?php else: ?>
                    <button onclick="openBanModal('<?= $u['id'] ?>')" class="text-red-800 hover:underline">Ban</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- VIEW USER MODAL -->
  <div id="userModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-2xl relative">
      <h2 class="text-xl font-semibold mb-4">View User</h2>
      <button onclick="closeModal('userModal')" class="absolute top-2 right-2">✖</button>
      <div id="userDetails">Loading...</div>
    </div>
  </div>

  <!-- EDIT USER MODAL -->
  <div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-lg relative">
      <h2 class="text-xl font-semibold mb-4">Edit User</h2>
      <button onclick="closeModal('editModal')" class="absolute top-2 right-2">✖</button>
      <form id="editUserForm" class="space-y-3">
        <input type="hidden" name="id" id="edit-user-id">
        <div>
          <label class="block mb-1">Username</label>
          <input type="text" name="username" id="edit-username" class="w-full border rounded px-3 py-2 dark:bg-gray-700">
        </div>
        <div>
          <label class="block mb-1">Email</label>
          <input type="email" name="email" id="edit-email" class="w-full border rounded px-3 py-2 dark:bg-gray-700">
        </div>
        <div>
          <label class="block mb-1">First Name</label>
          <input type="text" name="first_name" id="edit-first-name" class="w-full border rounded px-3 py-2 dark:bg-gray-700">
        </div>
        <div>
          <label class="block mb-1">Last Name</label>
          <input type="text" name="last_name" id="edit-last-name" class="w-full border rounded px-3 py-2 dark:bg-gray-700">
        </div>
        <div class="flex items-center gap-3">
          <label class="switch"><input type="checkbox" id="edit-is-business-owner"><span class="slider"></span></label>
          <span>Is business owner</span>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- DELETE CONFIRM -->
  <div id="deleteModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-md relative">
      <h2 class="text-lg font-semibold mb-3 text-red-600">Confirm Delete</h2>
      <p class="mb-4">Are you sure you want to delete this user? This action cannot be undone.</p>
      <div class="flex justify-end gap-3">
        <button onclick="closeModal('deleteModal')" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
        <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded">Delete</button>
      </div>
    </div>
  </div>

  <!-- BAN MODAL -->
  <div id="banModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-md relative">
      <h2 class="text-lg font-semibold mb-3 text-red-700">Ban User</h2>
      <button onclick="closeModal('banModal')" class="absolute top-2 right-2">✖</button>
      <form id="banForm" class="space-y-3">
        <input type="hidden" id="ban-user-id" name="id">
        <div>
          <label>Length (days, 0 = permanent)</label>
          <input type="number" id="ban-length" name="length" min="0" value="0" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label>Reason / Cause</label>
          <textarea id="ban-reason" name="reason" class="w-full border rounded px-3 py-2" rows="4"></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="closeModal('banModal')" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-red-700 text-white rounded">Ban user</button>
        </div>
      </form>
    </div>
  </div>

<script>
let selectedDeleteId = null;

function openModal(id){ document.getElementById(id).classList.remove('hidden'); }
function closeModal(id){ document.getElementById(id).classList.add('hidden'); }

// Fetch updated users list (refresh partial)
async function fetchUsers(){
  try {
    const res = await fetch('user-handler.php?action=list');
    const data = await res.json();
    if (!data.success) return alert('Failed to fetch users');
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '';
    if (data.users.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found.</td></tr>';
      return;
    }
    data.users.forEach(u => {
      const tr = document.createElement('tr');
      tr.className = 'border-t hover:bg-gray-50 dark:hover:bg-gray-800';
      tr.innerHTML = `
        <td class="px-6 py-4">
          <div class="flex items-center gap-3">
            <img src="${u.profile_image_url || 'https://via.placeholder.com/48'}" class="w-10 h-10 rounded-full object-cover" />
            <div>
              <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(u.username)}</div>
              <div class="text-xs text-gray-500">${escapeHtml((u.first_name||'') + ' ' + (u.last_name||''))}</div>
            </div>
          </div>
        </td>
        <td class="px-6 py-4">${escapeHtml(u.email)}</td>
        <td class="px-6 py-4">${u.is_business_owner ? '✅' : '—'}</td>
        <td class="px-6 py-4">${u.is_banned ? '<span class="text-red-600 font-semibold">Banned</span>' : '<span class="text-green-600">Active</span>'}</td>
        <td class="px-6 py-4 text-right">
          <button onclick="openUserModal('${u.id}')" class="text-green-600 mr-2">View</button>
          <button onclick="openEditModal('${u.id}')" class="text-blue-600 mr-2">Edit</button>
          <button onclick="openDeleteModal('${u.id}')" class="text-red-600 mr-2">Delete</button>
          ${u.is_banned ? `<button onclick="unbanUser('${u.id}')" class="text-yellow-600">Unban</button>` : `<button onclick="openBanModal('${u.id}')" class="text-red-800">Ban</button>`}
        </td>
      `;
      tbody.appendChild(tr);
    });
  } catch (err) {
    console.error(err);
    alert('Error fetching users');
  }
}

// view user
async function openUserModal(userId){
  openModal('userModal');
  document.getElementById('userDetails').innerText = 'Loading...';
  try {
    const res = await fetch('user-handler.php?action=view&id=' + userId);
    const data = await res.json();
    if (!data.success) {
      document.getElementById('userDetails').innerText = 'Error: ' + (data.error || 'Unknown');
      return;
    }
    const u = data.user;
    document.getElementById('userDetails').innerHTML = `
      <div class="grid grid-cols-2 gap-4">
        <div><p class="text-sm text-gray-500">ID</p><p class="font-medium">${u.id}</p></div>
        <div><p class="text-sm text-gray-500">Username</p><p class="font-medium">${escapeHtml(u.username)}</p></div>
        <div><p class="text-sm text-gray-500">First name</p><p class="font-medium">${escapeHtml(u.first_name||'')}</p></div>
        <div><p class="text-sm text-gray-500">Last name</p><p class="font-medium">${escapeHtml(u.last_name||'')}</p></div>
        <div class="col-span-2"><p class="text-sm text-gray-500">Email</p><p class="font-medium">${escapeHtml(u.email)}</p></div>
        <div class="col-span-2"><p class="text-sm text-gray-500">Bio</p><p class="font-medium">${escapeHtml(u.bio||'')}</p></div>
        <div><p class="text-sm text-gray-500">Location</p><p class="font-medium">${escapeHtml(u.location||'')}</p></div>
        <div><p class="text-sm text-gray-500">Status</p><p class="font-medium">${u.is_banned ? 'Banned' : 'Active'}</p></div>
      </div>
    `;
  } catch (err) {
    document.getElementById('userDetails').innerText = 'Error loading user';
  }
}

// open edit and populate
async function openEditModal(userId){
  openModal('editModal');
  document.getElementById('editUserForm').reset();
  try {
    const res = await fetch('user-handler.php?action=view&id=' + userId);
    const data = await res.json();
    if (!data.success) return alert('Could not load user');
    const u = data.user;
    document.getElementById('edit-user-id').value = u.id;
    document.getElementById('edit-username').value = u.username || '';
    document.getElementById('edit-email').value = u.email || '';
    document.getElementById('edit-first-name').value = u.first_name || '';
    document.getElementById('edit-last-name').value = u.last_name || '';
    document.getElementById('edit-is-business-owner').checked = !!u.is_business_owner;
  } catch (err) {
    alert('Error loading user for edit');
  }
}

document.getElementById('editUserForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);
  const res = await fetch('user-handler.php?action=edit', { method:'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    alert('User updated');
    closeModal('editModal');
    fetchUsers();
  } else {
    alert('Error: ' + (data.error || 'Unknown'));
  }
});

// delete
function openDeleteModal(id){
  selectedDeleteId = id;
  openModal('deleteModal');
}
document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
  if (!selectedDeleteId) return;
  const res = await fetch('user-handler.php?action=delete&id=' + selectedDeleteId, { method:'POST' });
  const data = await res.json();
  if (data.success) {
    alert('User deleted');
    closeModal('deleteModal');
    fetchUsers();
  } else {
    alert('Error deleting user');
  }
});

// ban
function openBanModal(id){
  document.getElementById('banForm').reset();
  document.getElementById('ban-user-id').value = id;
  openModal('banModal');
}
document.getElementById('banForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await fetch('user-handler.php?action=ban', { method:'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    alert('User banned and notified');
    closeModal('banModal');
    fetchUsers();
  } else {
    alert('Error banning user: ' + (data.error || 'Unknown'));
  }
});

async function unbanUser(id){
  if (!confirm('Unban this user?')) return;
  const res = await fetch('user-handler.php?action=unban&id=' + id, { method:'POST' });
  const data = await res.json();
  if (data.success) {
    alert('User unbanned');
    fetchUsers();
  } else {
    alert('Error unbanning user');
  }
}

function escapeHtml(unsafe) {
  if (unsafe === null || unsafe === undefined) return '';
  return String(unsafe)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// initial load
// if user provided a search via GET we already rendered; still allow refresh to get newest
</script>
</body>
</html>
