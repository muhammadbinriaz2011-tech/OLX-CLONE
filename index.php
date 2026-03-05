<?php
// Start session
session_start();

// Include database connection
require_once 'db.php';

// Initialize message system
if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}

function addMessage($text, $type = 'info') {
    $_SESSION['messages'][] = ['text' => $text, 'type' => $type];
}

function displayMessages() {
    if (!empty($_SESSION['messages'])) {
        foreach ($_SESSION['messages'] as $msg) {
            echo "<div class='alert alert-{$msg['type']} alert-dismissible fade show' role='alert'>
                    {$msg['text']}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                  </div>";
        }
        $_SESSION['messages'] = [];
    }
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserById($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

// Function to verify if user exists in database
function verifyUser($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

// Handle login
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            addMessage('Login successful!', 'success');
            header('Location: index.php');
            exit;
        } else {
            addMessage('Invalid email or password!', 'danger');
        }
    } catch(PDOException $e) {
        addMessage('Login error. Please try again.', 'danger');
    }
}

// Handle registration
if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $phone]);
        addMessage('Registration successful! Please login.', 'success');
    } catch(PDOException $e) {
        addMessage('Email already exists or registration failed!', 'danger');
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle ad posting - UPDATED WITH USER VERIFICATION
if (isset($_POST['post_ad'])) {
    if (!isLoggedIn()) {
        addMessage('Please login to post an ad!', 'warning');
    } else {
        // Verify user exists in database
        if (!verifyUser($_SESSION['user_id'])) {
            // User doesn't exist, logout and redirect
            session_destroy();
            addMessage('Your session has expired. Please login again.', 'warning');
            header('Location: index.php?page=login');
            exit;
        }
        
        // Get all form data
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $condition_type = $_POST['condition_type'] ?? 'used';
        $location = $_POST['location'] ?? '';
        
        // Validate required fields
        if (empty($title) || empty($description) || empty($price) || empty($category_id) || empty($location)) {
            addMessage('Please fill all required fields!', 'danger');
        } else {
            // Handle image upload
            $image = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $image = $target_dir . time() . '_' . basename($_FILES["image"]["name"]);
                
                if (!move_uploaded_file($_FILES["image"]["tmp_name"], $image)) {
                    addMessage('Error uploading image!', 'danger');
                    $image = ''; // Reset image variable if upload fails
                }
            }
            
            try {
                $stmt = $pdo->prepare("INSERT INTO ads (user_id, category_id, title, description, price, condition_type, location, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$_SESSION['user_id'], $category_id, $title, $description, $price, $condition_type, $location, $image]);
                
                if ($result) {
                    addMessage('Ad posted successfully!', 'success');
                    header('Location: index.php?page=my_ads');
                    exit;
                } else {
                    addMessage('Failed to post ad. Please try again.', 'danger');
                }
            } catch(PDOException $e) {
                // Show the actual error for debugging
                addMessage('Error posting ad: ' . $e->getMessage(), 'danger');
            }
        }
    }
}

// Handle message sending
if (isset($_POST['send_message'])) {
    if (!isLoggedIn()) {
        addMessage('Please login to send a message!', 'warning');
    } else {
        // Verify user exists in database
        if (!verifyUser($_SESSION['user_id'])) {
            // User doesn't exist, logout and redirect
            session_destroy();
            addMessage('Your session has expired. Please login again.', 'warning');
            header('Location: index.php?page=login');
            exit;
        }
        
        $ad_id = $_POST['ad_id'];
        $receiver_id = $_POST['receiver_id'];
        $message = $_POST['message'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (ad_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ad_id, $_SESSION['user_id'], $receiver_id, $message]);
            addMessage('Message sent successfully!', 'success');
        } catch(PDOException $e) {
            addMessage('Error sending message. Please try again.', 'danger');
        }
    }
}

// Handle ad deletion
if (isset($_GET['delete_ad'])) {
    if (isLoggedIn()) {
        // Verify user exists in database
        if (!verifyUser($_SESSION['user_id'])) {
            // User doesn't exist, logout and redirect
            session_destroy();
            addMessage('Your session has expired. Please login again.', 'warning');
            header('Location: index.php?page=login');
            exit;
        }
        
        $ad_id = $_GET['delete_ad'];
        try {
            $stmt = $pdo->prepare("UPDATE ads SET status = 'deleted' WHERE id = ? AND user_id = ?");
            $stmt->execute([$ad_id, $_SESSION['user_id']]);
            addMessage('Ad deleted successfully!', 'success');
        } catch(PDOException $e) {
            addMessage('Error deleting ad. Please try again.', 'danger');
        }
    }
    header('Location: index.php?page=my_ads');
    exit;
}

// Get page parameter
 $page = $_GET['page'] ?? 'home';
 $ad_id = $_GET['ad_id'] ?? null;
 $category_id = $_GET['category'] ?? null;
 $search = $_GET['search'] ?? '';

// Check if user is logged in and verify their existence in database
if (isLoggedIn() && !verifyUser($_SESSION['user_id'])) {
    session_destroy();
    addMessage('Your session has expired. Please login again.', 'warning');
    header('Location: index.php?page=login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OLX Clone - Buy and Sell Everything</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #002f34;
            --secondary-color: #00a8a8;
            --accent-color: #ffce32;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #008b8b;
            border-color: #008b8b;
        }
        
        .ad-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .ad-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .ad-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .price-tag {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        .category-icon {
            font-size: 2rem;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .search-box {
            border-radius: 20px;
            padding: 10px 20px;
            border: 2px solid var(--secondary-color);
        }
        
        .category-card {
            text-align: center;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .category-card:hover {
            background-color: var(--secondary-color);
            color: white;
            transform: scale(1.05);
        }
        
        .footer {
            background-color: var(--primary-color);
            color: white;
            margin-top: 50px;
            padding: 30px 0;
        }
        
        .badge-new {
            background-color: var(--accent-color);
            color: var(--primary-color);
        }
        
        .message-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shopping-bag"></i> OLX Clone
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=post_ad"><i class="fas fa-plus-circle"></i> Post Ad</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=my_ads"><i class="fas fa-list"></i> My Ads</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=messages"><i class="fas fa-envelope"></i> Messages</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars(getUserById($_SESSION['user_id'])['name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="index.php?page=profile"><i class="fas fa-user-circle"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="index.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=login"><i class="fas fa-sign-in-alt"></i> Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=register"><i class="fas fa-user-plus"></i> Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php displayMessages(); ?>
        
        <?php if ($page == 'home'): ?>
            <!-- Search Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="bg-white p-4 rounded shadow-sm">
                        <h2 class="mb-3">Find what you're looking for</h2>
                        <form method="GET" action="index.php">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <input type="text" name="search" class="form-control search-box" placeholder="Search for items..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Categories -->
            <h3 class="mb-3">Browse Categories</h3>
            <div class="row mb-5">
                <?php
                try {
                    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
                    while ($category = $stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="index.php?page=home&category=<?php echo $category['id']; ?>" class="category-card">
                            <div class="category-icon">
                                <i class="fas <?php echo $category['icon']; ?>"></i>
                            </div>
                            <h5><?php echo htmlspecialchars($category['name']); ?></h5>
                        </a>
                    </div>
                <?php 
                    endwhile;
                } catch(PDOException $e) {
                    echo "<div class='alert alert-danger'>Error loading categories. Please try again later.</div>";
                }
                ?>
            </div>

            <!-- Recent Ads / Search Results -->
            <h3 class="mb-3"><?php echo $search ? 'Search Results for "' . htmlspecialchars($search) . '"' : ($category_id ? 'Category: ' . getCategoryName($category_id) : 'Recent Ads'); ?></h3>
            <div class="row">
                <?php
                try {
                    // Base query
                    $query = "SELECT a.*, u.name as seller_name, c.name as category_name FROM ads a 
                             JOIN users u ON a.user_id = u.id 
                             JOIN categories c ON a.category_id = c.id 
                             WHERE a.status = 'active'";
                    $params = [];
                    
                    // Add search condition if search term exists
                    if (!empty($search)) {
                        // Split search term into words for better matching
                        $searchWords = explode(' ', trim($search));
                        $searchConditions = [];
                        
                        foreach ($searchWords as $word) {
                            if (!empty($word)) {
                                $searchConditions[] = "(LOWER(a.title) LIKE LOWER(?) OR LOWER(a.description) LIKE LOWER(?))";
                                $params[] = "%$word%";
                                $params[] = "%$word%";
                            }
                        }
                        
                        if (!empty($searchConditions)) {
                            $query .= " AND (" . implode(' OR ', $searchConditions) . ")";
                        }
                    }
                    
                    // Add category filter if category is selected
                    if (!empty($category_id)) {
                        $query .= " AND a.category_id = ?";
                        $params[] = $category_id;
                    }
                    
                    $query .= " ORDER BY a.created_at DESC LIMIT 20";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    
                    // Check if we have any results
                    $resultCount = 0;
                    while ($ad = $stmt->fetch(PDO::FETCH_ASSOC)):
                        $resultCount++;
                ?>
                    <div class="col-md-4">
                        <div class="card ad-card">
                            <?php if ($ad['image']): ?>
                                <img src="<?php echo htmlspecialchars($ad['image']); ?>" class="card-img-top ad-image" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                            <?php else: ?>
                                <img src="https://picsum.photos/seed/<?php echo $ad['id']; ?>/400/300.jpg" class="card-img-top ad-image" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($ad['title']); ?></h5>
                                <p class="card-text text-muted small">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($ad['category_name']); ?>
                                    <?php if ($ad['condition_type'] == 'new'): ?>
                                        <span class="badge badge-new ms-2">NEW</span>
                                    <?php endif; ?>
                                </p>
                                <p class="price-tag">$<?php echo number_format($ad['price'], 2); ?></p>
                                <p class="card-text">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ad['location']); ?>
                                </p>
                                <a href="index.php?page=ad_detail&ad_id=<?php echo $ad['id']; ?>" class="btn btn-primary w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                    
                    // Show message if no results found
                    if ($resultCount == 0 && (!empty($search) || !empty($category_id))) {
                        echo "<div class='col-12'><div class='alert alert-info'>No ads found matching your criteria.</div></div>";
                    }
                } catch(PDOException $e) {
                    echo "<div class='alert alert-danger'>Error loading ads: " . $e->getMessage() . "</div>";
                }
                ?>
            </div>

        <?php elseif ($page == 'login'): ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-center mb-4">Login</h3>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                            </form>
                            <p class="text-center mt-3">
                                Don't have an account? <a href="index.php?page=register">Register here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'register'): ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-center mb-4">Register</h3>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <button type="submit" name="register" class="btn btn-primary w-100">Register</button>
                            </form>
                            <p class="text-center mt-3">
                                Already have an account? <a href="index.php?page=login">Login here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'post_ad'): ?>
            <?php if (!isLoggedIn()): ?>
                <div class="alert alert-warning">Please login to post an ad!</div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title mb-4">Post New Ad</h3>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select name="category_id" class="form-select" required>
                                            <option value="">Select Category</option>
                                            <?php
                                            try {
                                                $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
                                                while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)):
                                            ?>
                                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                            <?php 
                                                endwhile;
                                            } catch(PDOException $e) {
                                                echo "<option value=''>Error loading categories</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="title" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="4" required></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Price ($)</label>
                                            <input type="number" name="price" step="0.01" class="form-control" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Condition</label>
                                            <select name="condition_type" class="form-select" required>
                                                <option value="used">Used</option>
                                                <option value="new">New</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <input type="text" name="location" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Image (optional)</label>
                                        <input type="file" name="image" class="form-control" accept="image/*">
                                    </div>
                                    <button type="submit" name="post_ad" class="btn btn-primary w-100">Post Ad</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($page == 'ad_detail' && $ad_id): ?>
            <?php
            try {
                $stmt = $pdo->prepare("SELECT a.*, u.name as seller_name, u.email as seller_email, c.name as category_name 
                                      FROM ads a 
                                      JOIN users u ON a.user_id = u.id 
                                      JOIN categories c ON a.category_id = c.id 
                                      WHERE a.id = ? AND a.status = 'active'");
                $stmt->execute([$ad_id]);
                $ad = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ad):
            ?>
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <?php if ($ad['image']): ?>
                                    <img src="<?php echo htmlspecialchars($ad['image']); ?>" class="img-fluid mb-3" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                <?php else: ?>
                                    <img src="https://picsum.photos/seed/<?php echo $ad['id']; ?>/800/600.jpg" class="img-fluid mb-3" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                <?php endif; ?>
                                <h2><?php echo htmlspecialchars($ad['title']); ?></h2>
                                <p class="text-muted">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($ad['category_name']); ?>
                                    <?php if ($ad['condition_type'] == 'new'): ?>
                                        <span class="badge badge-new ms-2">NEW</span>
                                    <?php endif; ?>
                                </p>
                                <h3 class="price-tag">$<?php echo number_format($ad['price'], 2); ?></h3>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ad['location']); ?></p>
                                <hr>
                                <h4>Description</h4>
                                <p><?php echo nl2br(htmlspecialchars($ad['description'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h4>Seller Information</h4>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($ad['seller_name']); ?></p>
                                <p><strong>Posted:</strong> <?php echo date('M d, Y', strtotime($ad['created_at'])); ?></p>
                                
                                <?php if (isLoggedIn() && $_SESSION['user_id'] != $ad['user_id']): ?>
                                    <hr>
                                    <h5>Contact Seller</h5>
                                    <form method="POST">
                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                        <input type="hidden" name="receiver_id" value="<?php echo $ad['user_id']; ?>">
                                        <div class="mb-3">
                                            <textarea name="message" class="form-control" rows="3" placeholder="Type your message..." required></textarea>
                                        </div>
                                        <button type="submit" name="send_message" class="btn btn-primary w-100">
                                            <i class="fas fa-paper-plane"></i> Send Message
                                        </button>
                                    </form>
                                <?php elseif (!isLoggedIn()): ?>
                                    <hr>
                                    <a href="index.php?page=login" class="btn btn-primary w-100">
                                        <i class="fas fa-sign-in-alt"></i> Login to Contact Seller
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                else:
                    echo "<div class='alert alert-danger'>Ad not found!</div>";
                endif;
            } catch(PDOException $e) {
                echo "<div class='alert alert-danger'>Error loading ad. Please try again later.</div>";
            }
            ?>

        <?php elseif ($page == 'my_ads'): ?>
            <?php if (!isLoggedIn()): ?>
                <div class="alert alert-warning">Please login to view your ads!</div>
            <?php else: ?>
                <h2 class="mb-4">My Ads</h2>
                <div class="row">
                    <?php
                    try {
                        $stmt = $pdo->prepare("SELECT a.*, c.name as category_name FROM ads a 
                                             JOIN categories c ON a.category_id = c.id 
                                             WHERE a.user_id = ? AND a.status != 'deleted' 
                                             ORDER BY a.created_at DESC");
                        $stmt->execute([$_SESSION['user_id']]);
                        
                        while ($ad = $stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                        <div class="col-md-4">
                            <div class="card ad-card">
                                <?php if ($ad['image']): ?>
                                    <img src="<?php echo htmlspecialchars($ad['image']); ?>" class="card-img-top ad-image" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                <?php else: ?>
                                    <img src="https://picsum.photos/seed/<?php echo $ad['id']; ?>/400/300.jpg" class="card-img-top ad-image" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($ad['title']); ?></h5>
                                    <p class="card-text text-muted small">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($ad['category_name']); ?>
                                        <?php if ($ad['condition_type'] == 'new'): ?>
                                            <span class="badge badge-new ms-2">NEW</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="price-tag">$<?php echo number_format($ad['price'], 2); ?></p>
                                    <p class="card-text">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ad['location']); ?>
                                    </p>
                                    <div class="btn-group w-100" role="group">
                                        <a href="index.php?page=ad_detail&ad_id=<?php echo $ad['id']; ?>" class="btn btn-outline-primary">View</a>
                                        <a href="index.php?delete_ad=<?php echo $ad['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    } catch(PDOException $e) {
                        echo "<div class='alert alert-danger'>Error loading your ads. Please try again later.</div>";
                    }
                    ?>
                </div>
            <?php endif; ?>

        <?php elseif ($page == 'messages'): ?>
            <?php if (!isLoggedIn()): ?>
                <div class="alert alert-warning">Please login to view messages!</div>
            <?php else: ?>
                <h2 class="mb-4">My Messages</h2>
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT m.*, a.title as ad_title, u.name as sender_name 
                                         FROM messages m 
                                         JOIN ads a ON m.ad_id = a.id 
                                         JOIN users u ON m.sender_id = u.id 
                                         WHERE m.receiver_id = ? 
                                         ORDER BY m.created_at DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    while ($message = $stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                    <div class="message-box">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong>
                                <small class="text-muted">about: <?php echo htmlspecialchars($message['ad_title']); ?></small>
                            </div>
                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></small>
                        </div>
                        <p class="mt-2 mb-0"><?php echo htmlspecialchars($message['message']); ?></p>
                    </div>
                <?php 
                    endwhile;
                } catch(PDOException $e) {
                    echo "<div class='alert alert-danger'>Error loading messages. Please try again later.</div>";
                }
                ?>
            <?php endif; ?>

        <?php elseif ($page == 'profile'): ?>
            <?php if (!isLoggedIn()): ?>
                <div class="alert alert-warning">Please login to view your profile!</div>
            <?php else: ?>
                <?php
                try {
                    $user = getUserById($_SESSION['user_id']);
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total_ads FROM ads WHERE user_id = ? AND status != 'deleted'");
                    $stmt->execute([$_SESSION['user_id']]);
                    $ads_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_ads'];
                ?>
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
                                    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></p>
                                    <hr>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <h4><?php echo $ads_count; ?></h4>
                                            <p class="text-muted">Total Ads</p>
                                        </div>
                                        <div class="col-6">
                                            <h4><?php echo date('Y', strtotime($user['created_at'])); ?></h4>
                                            <p class="text-muted">Member Since</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                } catch(PDOException $e) {
                    echo "<div class='alert alert-danger'>Error loading profile. Please try again later.</div>";
                }
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>OLX Clone</h5>
                    <p>Buy and sell everything locally!</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white-50">Home</a></li>
                        <li><a href="index.php?page=post_ad" class="text-white-50">Post Ad</a></li>
                        <li><a href="index.php?page=login" class="text-white-50">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <p class="text-white-50">
                        <i class="fas fa-envelope"></i> support@olxclone.com<br>
                        <i class="fas fa-phone"></i> +123 456 7890
                    </p>
                </div>
            </div>
            <hr class="bg-white-50">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> OLX Clone. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Function to get category name by ID
function getCategoryName($category_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        return $category ? $category['name'] : 'Unknown';
    } catch(PDOException $e) {
        return 'Unknown';
    }
}
?>
