<?php
echo password_hash("admin123", PASSWORD_DEFAULT);
    // Output: $2y$10$eW5z1Z3b1a8f5c9d7e8f9uO6h3j4k5l6m7n8o9p0q1r2s3t4u5v6w7x8y9z
    // Note: The output will vary each time you run this due to the use of a random salt.
    // This code generates a hashed password for the string "admin123" using the bcrypt algorithm.
    // You can use this hash to store in your database for user authentication.
    // Make sure to run this code in a secure environment and do not expose the hash publicly.
    // This code is for generating a password hash for the admin user.
    