How to Run the Project
----------------------
1- Clone the repository:-

1.1- Open your terminal or Git Bash and run: git clone https://github.com/KarimEssac/Scout_Management_System

2- Move the project to XAMPP

3- Copy the cloned folder to your htdocs directory (usually found in C:\xampp\htdocs).

4- Start XAMPP

5- Open XAMPP Control Panel.

6- Start Apache and MySQL.

7- Open your browser and go to phpMyAdmin.

7- Create a new database (Use the provided structure in the file: DB link.txt ,it contains the link or structure of the database.)

8- Run the Project

8.1- Visit http://localhost/your-project-folder in your browser.

📂 Notes

Not all files have been uploaded. Some important files were intentionally excluded to maintain code confidentiality and protect the project from unauthorized use.

🗃️ Database

The database structure is included and can be found in the file: DB link.txt.

👤 Ownership

This work is 70% personal effort and 30% assisted by AI tools for optimization and support.

📘 What is phpqrcode?

phpqrcode is an open-source PHP library used to generate QR codes. It allows developers to create QR codes from any text or data using pure PHP without needing external dependencies or APIs.

✅ Why It's Important in This Project

In this Scout Administration System, QR codes are used to:

Generate unique QR codes for each scout based on their stored information.

Allow quick scanning for attendance or data retrieval.

Enhance system interactivity and efficiency.

The phpqrcode library makes it easy to dynamically generate these codes for every user in the database.

📁 Important Note

You need to create a folder named qr_code inside the project directory.

This folder will be used to store all the generated QR code images for each user's data.

Make sure this folder has write permissions so that the QR code images can be saved successfully.

