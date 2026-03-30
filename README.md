## 🗄️ MySQL Database Compare Tool (Core PHP)

This is a lightweight Core PHP web application that allows you to compare two MySQL databases and identify structural differences between them.

### 🚀 Features

- Select any two databases from the same MySQL server
- Compare database structures بسهولة
- Detect:
  - ✅ Missing tables in each database
  - ✅ Common tables between databases
  - ✅ Column differences in matching tables
- Clean and simple UI
- Built using pure PHP (no frameworks)

### 🔍 What It Compares

1. **Tables**
   - Tables present in DB1 but missing in DB2
   - Tables present in DB2 but missing in DB1

2. **Columns (for common tables)**
   - Missing columns in each database
   - Optional column type differences

### ⚙️ Tech Stack

- Core PHP
- MySQL
- HTML/CSS
- mysqli / PDO
- INFORMATION_SCHEMA for metadata queries

### 📂 Project Structure
