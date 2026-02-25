<?php include '../includes/header.php'; ?>

<h2>Create Account</h2>

<form action="process-signup.php" method="POST">
    <input type="text" name="full_name" placeholder="Full Name" required><br><br>
    
    <input type="email" name="email" placeholder="Email" required><br><br>
    
    <input type="text" name="phone" placeholder="Phone Number"><br><br>
    
    <input type="password" name="password" placeholder="Password" required><br><br>
    
    <button type="submit">Sign Up</button>
</form>

<?php include '../includes/footer.php'; ?>