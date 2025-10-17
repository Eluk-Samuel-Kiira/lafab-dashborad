<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs Dashboard - LaFab Solutions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Mobile Header -->
    <nav class="mobile-header d-lg-none">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <button class="menu-toggle" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                    <img src="../logo.svg" alt="LaFab Solutions" height="30" class="me-2">
                    LaFab Solutions
                </a>
                <div></div> <!-- Spacer for balance -->
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <div class="container-fluid">
        <div class="row">


<!-- 
        http://localhost/job-dashboard/init_db.php
        This app uses sqlite so if you want to crash the database to have a new one
        Run the above link in the browser
-->