<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
$partnersFile = "../data/partners.json";
$partners = file_exists($partnersFile) ? json_decode(file_get_contents($partnersFile), true) : [];
// Sort partners alphabetically by name
usort($partners, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});
?>

<?php if(canACcess()): ?>

<?php getHeader() ?>
    <title>Learning Partners</title>
    <script src="/lsapp/js/list.min.js"></script>
    <script>
        function editPartner(partner) {
            localStorage.setItem("editPartner", JSON.stringify(partner));
            window.location.href = "form.php";
        }
        function deletePartner(id, name) {
            if (confirm(`Are you sure you want to delete '${name}'? This cannot be undone.`)) {
                fetch('process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `delete_id=${id}`
                }).then(response => response.text()).then(data => {
                    alert(data);
                    window.location.reload();
                }).catch(error => console.error('Error:', error));
            }
        }
    </script>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<div class="container-lg p-lg-5 p-4 bg-light-subtle">
        <h1>Corporate Learning Partners</h1>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">Partner Admin Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="index.php">Partner List</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="form.php">Add New Partner</a>
            </li>
        </ul>
        
        
        <div id="partner-list">
            <input class="search form-control mb-3" placeholder="Search partners...">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th class="sort" data-sort="status" style="cursor: pointer;">
                            <a href="#" class="text-decoration-none">Status</a> 
                            <i class="fas fa-sort" style="font-size: 0.7em; opacity: 0.6;"></i>
                        </th>
                        <th class="sort" data-sort="name" style="cursor: pointer;">
                            <a href="#" class="text-decoration-none">Partner Name</a> 
                            <i class="fas fa-sort" style="font-size: 0.7em; opacity: 0.6;"></i>
                        </th>
                        <th class="sort" data-sort="contact" style="cursor: pointer;">
                            <a href="#" class="text-decoration-none">Employee Support Contact</a> 
                            <i class="fas fa-sort" style="font-size: 0.7em; opacity: 0.6;"></i>
                        </th>
                        <th>Contacts</th>
                        <th>Links</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php foreach ($partners as $partner): ?>
                        <tr>
                            <td class="status">
                                <?php 
                                $status = $partner["status"] ?? 'Unknown';
                                $badgeClass = 'bg-secondary-subtle text-secondary-emphasis';
                                if ($status === 'Active') {
                                    $badgeClass = 'bg-success-subtle text-success-emphasis';
                                } elseif ($status === 'Inactive') {
                                    $badgeClass = 'bg-danger-subtle text-danger-emphasis';
                                }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td class="name">
                                <a href="/lsapp/partners/view.php?slug=<?php echo htmlspecialchars($partner["slug"]); ?>">
                                    <?php echo htmlspecialchars($partner["name"]); ?>
                                </a>
                            </td>
                            <td class="contact">
                                <?php if (!empty($partner["employee_facing_contact"])): ?>
                                    <?php if ($partner["employee_facing_contact"] === "CRM"): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis">CRM</span>
                                    <?php else: ?>
                                        <a href="mailto:<?php echo htmlspecialchars($partner["employee_facing_contact"]); ?>">
                                            <?php echo htmlspecialchars($partner["employee_facing_contact"]); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Not specified</span>
                                <?php endif; ?>
                            </td>
                            <td class="contacts">
                                <?php if (!empty($partner["contacts"]) && is_array($partner["contacts"])): ?>
                                    <?php foreach ($partner["contacts"] as $contact): ?>
                                        <?php 
                                        $nameDisplay = htmlspecialchars($contact["name"]);
                                        if (!empty($contact["role"]) && $contact["role"] !== "Unknown") {
                                            $nameDisplay .= ", " . htmlspecialchars($contact["role"]);
                                        }
                                        ?>
                                        <?php if ($contact["email"] === "unknown@gov.bc.ca"): ?>
                                            <?php echo $nameDisplay; ?><br>
                                        <?php else: ?>
                                            <a href="mailto:<?php echo htmlspecialchars($contact["email"]); ?>">
                                                <?php echo $nameDisplay; ?>
                                            </a><br>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">No contacts</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo htmlspecialchars($partner["link"]); ?>" class="btn btn-sm btn-link" target="_blank">
                                    LearningHUB
                                </a>
                                <a href="https://gww.bcpublicservice.gov.bc.ca/learning/hub/partners/dashboard.php?partnerid=<?php echo htmlspecialchars($partner["id"]); ?>" class="btn btn-sm btn-link" target="_blank">
                                    Admin Panel
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
</div>

<script>
    var options = {
        valueNames: ['status', 'name', 'contact', 'contacts']
    };
    var partnerList = new List('partner-list', options);
</script>

<?php endif ?>

<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>
