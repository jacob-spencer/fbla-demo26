<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require '/home/u414139450/vendor/autoload.php';
require_once 'config.php';
require_once 'functions.php';

$db = Database::getInstance();

// Handle maintenance toggle AJAX
$statusFile = __DIR__ . '/../maintenance.flag';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if ($_GET['action'] === 'set') {
        if (!empty($input['enabled'])) {
            file_put_contents($statusFile, 'on');
        } else {
            if (file_exists($statusFile)) unlink($statusFile);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    if ($_GET['action'] === 'get') {
        echo json_encode(['enabled' => file_exists($statusFile)]);
        exit;
    }
}

function searchBusinesses($params) {
    global $db;
    $sql = "SELECT b.*, c.name as category_name, c.icon as category_icon,
            u.username, u.first_name, u.last_name, u.email as owner_email,
            CONCAT(u.first_name, ' ', u.last_name) as owner_name
            FROM businesses b 
            LEFT JOIN categories c ON b.category_id = c.id 
            LEFT JOIN users u ON b.owner_id = u.id
            WHERE 1=1";
    $sqlParams = [];

    if (!empty($params['search'])) {
        $sql .= " AND (b.name LIKE ? OR b.description LIKE ?)";
        $term = "%{$params['search']}%";
        $sqlParams[] = $term;
        $sqlParams[] = $term;
    }

    if (!empty($params['category'])) {
        $category = $db->fetch(
            "SELECT id FROM categories WHERE slug = ? OR id = ? LIMIT 1",
            [$params['category'], $params['category']]
        );
        if ($category) {
            $sql .= " AND b.category_id = ?";
            $sqlParams[] = $category['id'];
        }
    }

    if (!empty($params['price']) && $params['price'] !== 'all') {
        $sql .= " AND b.price_range = ?";
        $sqlParams[] = $params['price'];
    }

    $businesses = $db->fetchAll($sql, $sqlParams);

    if ($params['open'] !== null && $params['open'] !== '') {
        $businesses = array_filter($businesses, function($b) use ($params) {
            $openingHours = $b['opening_hours'] ?? '';
            $timezone = $b['timezone'] ?? 'UTC';
            $open = isBusinessOpen($openingHours, $timezone);
            return $params['open'] ? $open : !$open;
        });
    }

    return $businesses;
}
// Fetch all businesses for dashboard display
$params = [
    'search' => $_GET['search'] ?? '',
    'category' => $_GET['category'] ?? '',
    'price' => $_GET['price'] ?? '',
    'sort' => 'name',
    'open' => null
];
$businesses = searchBusinesses($params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }
        .switch input {display: none;}
        .slider {
            position: absolute;
            cursor: pointer;
            background-color: #ccc;
            border-radius: 28px;
            top: 0; left: 0; right: 0; bottom: 0;
            transition: .4s;
        }
        .slider:before {
            content: "";
            position: absolute;
            height: 20px; width: 20px;
            left: 4px; bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #4CAF50; }
        input:checked + .slider:before { transform: translateX(22px); }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">

<!-- SIDEBAR TOGGLE -->
<button data-drawer-target="default-sidebar" data-drawer-toggle="default-sidebar" aria-controls="default-sidebar"
        type="button" class="inline-flex items-center p-2 mt-2 ms-3 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 dark:hover:bg-gray-700">
   <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path clip-rule="evenodd" fill-rule="evenodd"
       d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"/></svg>
</button>

<!-- SIDEBAR -->
<aside id="default-sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0"
        aria-label="Sidebar">
   <div class="h-full px-3 py-4 overflow-y-auto bg-gray-50 dark:bg-gray-800">
      <ul class="space-y-2 font-medium">
         <li><a href="/" class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">🏠 Dashboard</a></li>
         <li><a href="notifications" class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">🔔 Notifications</a></li>
         <li><a href="user-management" class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">👥 User Management</a></li>
         <li><a href="business-management" class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg font-semibold text-blue-600">🏢 Business Management</a></li>
         <li><a href="security" class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">🔒 Security</a></li>
         <li><a href="settings" class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">⚙️ Settings</a></li>
      </ul>
   </div>
</aside>

<!-- MAIN CONTENT -->
<div class="p-4 sm:ml-64">
    <h1 class="text-2xl font-semibold mb-6">Business Management</h1>

    <!-- Search -->
    <form method="GET" class="mb-6 flex gap-3">
        <input type="text" name="search" placeholder="Search businesses..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
               class="border border-gray-300 rounded-md px-4 py-2 w-1/2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Search</button>
    </form>

    <!-- Businesses Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                <tr>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Category</th>
                    <th class="px-6 py-3">Owner</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($businesses)): ?>
                    <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No businesses found.</td></tr>
                <?php else: ?>
                    <?php foreach ($businesses as $b): ?>
                        <tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($b['name']) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($b['category_name'] ?? '—') ?></td>
                            <td class="px-6 py-4">
                                <?php if (!empty($b['owner_id'])): ?>
                                    <button onclick="openUserModal('<?= $b['owner_id'] ?>')" class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($b['owner_name'] ?? 'N/A') ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?= !empty($b['approved']) ? '<span class="text-green-600 font-semibold">Active</span>' : '<span class="text-gray-400">Pending</span>' ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="openViewModal('<?= $b['id'] ?>')" class="text-green-600 hover:underline">View</button> |
                                <button onclick="openEditModal('<?= $b['id'] ?>')" class="text-blue-600 hover:underline">Edit</button> |
                                <button onclick="openDeleteModal('<?= $b['id'] ?>')" class="text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- VIEW USER MODAL -->
<div id="userModal" tabindex="-1" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-2xl relative">
    <h2 class="text-xl font-semibold mb-4">View User Details</h2>
    <button onclick="closeModal('userModal')" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700">✖</button>
    <div id="userContent" class="space-y-3 text-gray-700 dark:text-gray-200">
      Loading...
    </div>
  </div>
</div>

<!-- VIEW MODAL -->
<div id="viewModal" tabindex="-1" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-lg relative">
    <h2 class="text-xl font-semibold mb-4">View Business</h2>
    <button onclick="closeModal('viewModal')" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700">✖</button>
    <div id="viewContent" class="space-y-2 text-gray-700 dark:text-gray-200">
      Loading...
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" tabindex="-1" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-lg relative">
    <h2 class="text-xl font-semibold mb-4">Edit Business</h2>
    <button onclick="closeModal('editModal')" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700">✖</button>
    <form id="editForm" class="space-y-3">
      <input type="hidden" name="id" id="edit-id">
      <div>
        <label class="block mb-1">Name</label>
        <input type="text" name="name" id="edit-name" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
      </div>
      <div>
        <label class="block mb-1">Description</label>
        <textarea name="description" id="edit-description" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600"></textarea>
      </div>
      <div>
        <label class="block mb-1">Category</label>
        <input type="text" name="category" id="edit-category" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
      </div>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
    </form>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div id="deleteModal" tabindex="-1" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-md relative">
    <h2 class="text-lg font-semibold mb-3 text-red-600">Confirm Delete</h2>
    <p class="mb-4 text-gray-700 dark:text-gray-200">Are you sure you want to delete this business?</p>
    <div class="flex justify-end gap-3">
      <button onclick="closeModal('deleteModal')" class="px-4 py-2 bg-gray-300 dark:bg-gray-700 rounded hover:bg-gray-400">Cancel</button>
      <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
    </div>
  </div>
</div>

<script>
function openModal(id) {
  document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
  document.getElementById(id).classList.add('hidden');
}

// === VIEW USER ===
async function openUserModal(userId) {
  openModal('userModal');
  try {
    const res = await fetch('user-handler.php?action=view&id=' + userId);
    const data = await res.json();
    
    const formatDate = (dateStr) => {
      if (!dateStr) return 'N/A';
      return new Date(dateStr).toLocaleString();
    };
    
    document.getElementById('userContent').innerHTML = `
      <div class="grid grid-cols-2 gap-4">
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">User ID</p>
          <p class="font-medium">${data.id || 'N/A'}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Username</p>
          <p class="font-medium">${data.username || 'N/A'}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">First Name</p>
          <p class="font-medium">${data.first_name || 'N/A'}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Last Name</p>
          <p class="font-medium">${data.last_name || 'N/A'}</p>
        </div>
        <div class="col-span-2">
          <p class="text-sm text-gray-500 dark:text-gray-400">Email</p>
          <p class="font-medium">${data.email || 'N/A'}</p>
        </div>
        <div class="col-span-2">
          <p class="text-sm text-gray-500 dark:text-gray-400">Location</p>
          <p class="font-medium">${data.location || 'N/A'}</p>
        </div>
        <div class="col-span-2">
          <p class="text-sm text-gray-500 dark:text-gray-400">Bio</p>
          <p class="font-medium">${data.bio || 'N/A'}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Business Owner</p>
          <p class="font-medium">${data.is_business_owner ? '✅ Yes' : '❌ No'}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Created At</p>
          <p class="font-medium text-sm">${formatDate(data.created_at)}</p>
        </div>
        ${data.profile_image_url ? `
        <div class="col-span-2">
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Profile Image</p>
          <img src="${data.profile_image_url.startsWith('http://') || data.profile_image_url.startsWith('https://') ? 
                    data.profile_image_url : 
                    'https://mainstreet.lt/' + data.profile_image_url}" 
       alt="Profile" 
       class="w-24 h-24 rounded-full object-cover">
        </div>
        ` : ''}
      </div>
    `;
  } catch (error) {
    document.getElementById('userContent').innerHTML = `
      <p class="text-red-600">Error loading user details: ${error.message}</p>
    `;
  }
}

// === VIEW BUSINESS ===
async function openViewModal(id) {
  openModal('viewModal');
  const res = await fetch('business-handler.php?action=view&id=' + id);
  const data = await res.json();
  document.getElementById('viewContent').innerHTML = `
    <p><strong>Name:</strong> ${data.name}</p>
    <p><strong>Description:</strong> ${data.description}</p>
    <p><strong>Category:</strong> ${data.category_name ?? '—'}</p>
    <p><strong>Owner:</strong> ${data.owner_name ?? '—'}</p>
    <p><strong>Status:</strong> ${data.approved ? 'Active' : 'Pending'}</p>
  `;
}

// === EDIT BUSINESS ===
async function openEditModal(id) {
  openModal('editModal');
  const res = await fetch('business-handler.php?action=view&id=' + id);
  const data = await res.json();
  document.getElementById('edit-id').value = data.id;
  document.getElementById('edit-name').value = data.name;
  document.getElementById('edit-description').value = data.description;
  document.getElementById('edit-category').value = data.category_name ?? '';
}

document.getElementById('editForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const res = await fetch('business-handler.php?action=edit', {
    method: 'POST',
    body: formData
  });
  const data = await res.json();
  if (data.success) {
    alert('Business updated!');
    closeModal('editModal');
    location.reload();
  } else {
    alert('Error updating business');
  }
});

// === DELETE BUSINESS ===
let deleteId = null;
function openDeleteModal(id) {
  deleteId = id;
  openModal('deleteModal');
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
  const res = await fetch('business-handler.php?action=delete&id=' + deleteId, { method: 'POST' });
  const data = await res.json();
  if (data.success) {
    closeModal('deleteModal');
    location.reload();
  } else {
    alert('Error deleting business');
  }
});
</script>

<!-- JS for Maintenance Toggle -->
<script>
async function updateToggle() {
    const res = await fetch('?action=get', { method: 'POST' });
    const data = await res.json();
    const toggle = document.getElementById('maintenance-toggle');
    const status = document.getElementById('maintenance-status');
    toggle.checked = data.enabled;
    status.textContent = data.enabled ? 'Enabled' : 'Disabled';
}

document.getElementById('maintenance-toggle').addEventListener('change', async (e) => {
    const enabled = e.target.checked;
    await fetch('?action=set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ enabled })
    });
    updateToggle();
});

updateToggle();
</script>

</body>
</html>