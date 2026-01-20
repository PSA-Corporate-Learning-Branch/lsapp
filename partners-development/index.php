<?php
require('../inc/lsapp.php');

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Load development partners
$partners = getAllDevPartners();

?>
<?php getHeader(); ?>
<title>Development Partners</title>

<?php getScripts(); ?>

<body>
<?php getNavigation(); ?>
<div class="container">
    <h1>Corporate Learning Partners</h1>

    <?php include('../templates/partner-nav.php'); ?>

    <div class="row">
        <div class="col-12">
            <div class="float-end">
                <a href="create.php" class="btn btn-primary">Add New Partner</a>
            </div>
            <p>A course can have one or more development partners associated with it.</p>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($partners)): ?>
                <div class="alert alert-info">
                    No development partners found. <a href="create.php">Add your first partner</a>.
                </div>
            <?php else: ?>
                <div class="table-responsive mt-4">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Type</th>
                                <th>URL</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partners as $partner): ?>
                                <tr>
                                    <td><?= htmlspecialchars($partner['id']) ?></td>
                                    <td>
                                        <strong><a href="view.php?id=<?= htmlspecialchars($partner['id']) ?>"><?= htmlspecialchars($partner['name']) ?></a></strong>
                                        <?php if (!empty($partner['description'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($partner['description'], 0, 100)) ?><?= strlen($partner['description']) > 100 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = 'bg-secondary-subtle text-secondary-emphasis';
                                        if ($partner['status'] === 'active') {
                                            $badgeClass = 'bg-success-subtle text-success-emphasis';
                                        } elseif ($partner['status'] === 'inactive') {
                                            $badgeClass = 'bg-danger-subtle text-danger-emphasis';
                                        }
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars(ucfirst($partner['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst($partner['type'])) ?></td>
                                    <td>
                                        <?php if (!empty($partner['url'])): ?>
                                            <a href="<?= htmlspecialchars($partner['url']) ?>" target="_blank" rel="noopener">
                                                Link <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($partner['contact_name']) || !empty($partner['contact_email'])): ?>
                                            <?= htmlspecialchars($partner['contact_name']) ?>
                                            <?php if (!empty($partner['contact_email'])): ?>
                                                <br><small><a href="mailto:<?= htmlspecialchars($partner['contact_email']) ?>"><?= htmlspecialchars($partner['contact_email']) ?></a></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="update.php?id=<?= htmlspecialchars($partner['id']) ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-outline-danger"
                                                    onclick="confirmDelete('<?= htmlspecialchars($partner['id']) ?>', '<?= htmlspecialchars(addslashes($partner['name'])) ?>')"
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted">Total: <?= count($partners) ?> partner(s)</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong id="deletePartnerName"></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="controller.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deletePartnerId">
                    <?php csrfField(); ?>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deletePartnerId').value = id;
    document.getElementById('deletePartnerName').textContent = name;
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php

include('../templates/javascript.php');
include('../templates/footer.php');
?>
</body>