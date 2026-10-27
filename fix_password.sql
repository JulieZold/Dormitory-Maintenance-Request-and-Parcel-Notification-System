USE dormitory_system;

UPDATE users 
SET password = '$2y$12$q1lSux70Wp6UIBsGQWODPelkIDOZswholkezrqeoBapA9xgkImliW', role = 'admin', must_change_password = 0
WHERE username = 'admin';

UPDATE users 
SET password = '$2y$12$ZM3HPQ0w8vRe1R8oPUzkGuyYg175Pc9y/KJPuuFBW5znPjkY1Tw3a', role = 'user', must_change_password = 1
WHERE username IN ('room101','room102','room103','room303','room211');
