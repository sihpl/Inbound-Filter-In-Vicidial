# 📞 Inbound Filter in VICIdial

Whitelist-based AGI script to filter inbound calls in VICIdial using PHP and MySQL.

[![GitHub Repo](https://img.shields.io/badge/GitHub-sihpl/Inbound--Filter--In--Vicidial-blue?logo=github)](https://github.com/sihpl/Inbound-Filter-In-Vicidial)

---

## 🧠 Features

✅ Blocks inbound calls not on your whitelist  
✅ Tracks call counts per Caller ID (daily)  
✅ Limits each CLI to **5 calls per day**  
✅ Fully PHP-based AGI implementation  
✅ Easy integration via `extensions.conf`  
✅ Clean logging with lead tracking

---

## 📁 Files

| File                        | Description                       |
|----------------------------|-----------------------------------|
| `whitelist.php`            | Main AGI script for filtering     |
| `phpagi.php`               | PHP AGI helper library            |
| `phpagi-asmanager.php`     | PHP AGI Asterisk Manager helper   |

---

## 🛠️ Installation Steps

### 🔧 Step 1: Update Dialplan

Edit `/etc/asterisk/extensions.conf`

**Replace this:**
```asterisk
[trunkinbound]
exten => _X.,1,AGI(agi-DID_route.agi)
exten => _X.,n,Hangup()
```

**With this:**
```asterisk
[trunkinbound]
exten => _X.,1,AGI(/usr/src/agi-scripts/whitelist.php,${CALLERID(num)})
exten => _X.,n,AGI(agi-DID_route.agi)
exten => _X.,n,Hangup()
```

---

### 📁 Step 2: Create AGI Scripts Directory

```bash
cd /usr/src/
mkdir agi-scripts
```

---

### 👅 Step 3: Download AGI Scripts

```bash
cd /usr/src/agi-scripts

wget https://raw.githubusercontent.com/sihpl/Inbound-Filter-In-Vicidial/main/agi-scripts/phpagi-asmanager.php
wget https://raw.githubusercontent.com/sihpl/Inbound-Filter-In-Vicidial/main/agi-scripts/phpagi.php
wget https://raw.githubusercontent.com/sihpl/Inbound-Filter-In-Vicidial/main/agi-scripts/whitelist.php
```

---

### 🔐 Step 4: Set Permissions

```bash
chmod -R 755 /usr/src/agi-scripts/*.php
```

---

### 🔄 Step 5: Reload Dialplan

```bash
asterisk -rx "dialplan reload"
```

---

## 🗃️ Step 6: Create Call Count Table

Login to MySQL:

```
mysql -u root -p asterisk
```

Run the SQL command:

```sql
CREATE TABLE cli_call_logs_all (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caller_id VARCHAR(20),
    call_date DATE,
    call_time DATETIME,
    call_status ENUM('ALLOWED', 'BLOCKED_WHITELIST', 'BLOCKED_LIMIT') NOT NULL,
    lead_id INT DEFAULT NULL
);
'''

## 🪜 Step 7: Auto-Reset Call Count Daily

Edit crontab:

```bash
crontab -e
```

Add this line:

```bash
0 1 * * * mysql -u root -p'your_mysql_password' asterisk -e "DELETE FROM cli_call_logs_all WHERE call_date < CURDATE() - INTERVAL 30 DAY;"
```

> 📉 This clears previous day’s call records automatically.

---

## ✅ Testing

- ❌ Call from a number **not in whitelist** → will be blocked.
- ✅ Call from a **whitelisted number** → allowed **up to 5 times per day**.
- 📄 Check `/tmp/whitelist.log` for debug and flow.

---

## 🧪 Tested On

- VICIdial with Asterisk 16
- ViciBox 11 with OpenSUSE Leap 15.5

---

## 🤝 Contributing

Pull Requests are welcome!

---

## 📜 License

MIT License

