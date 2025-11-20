<?php
require('../inc/lsapp.php');

echo getHeader('Add Development Partner');
echo getNavigation();
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Add Development Partner</h1>
                <a href="index.php" class="btn btn-outline-secondary">Back to List</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="controller.php">
                        <input type="hidden" name="action" value="create">
                        <?php csrfField(); ?>

                        <div class="mb-3">
                            <label for="name" class="form-label">Partner Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="development" selected>Development</option>
                                    <option value="external">External</option>
                                    <option value="government">Government</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                            <div class="form-text">Brief description of the partner organization.</div>
                        </div>

                        <div class="mb-3">
                            <label for="url" class="form-label">Website URL</label>
                            <input type="url" class="form-control" id="url" name="url" placeholder="https://example.com">
                        </div>

                        <hr>
                        <h5>Contact Information</h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_name" class="form-label">Contact Name</label>
                                <input type="text" class="form-control" id="contact_name" name="contact_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email">
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Partner</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
echo getScripts();
include('../templates/footer.php');
?>
