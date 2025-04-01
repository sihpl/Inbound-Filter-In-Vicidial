# 📞 Inbound Filter for VICIdial  
**Whitelist-based AGI script to filter inbound calls using PHP + MySQL**

[![GitHub Repo](https://img.shields.io/badge/GitHub-sihpl%2FInbound--Filter--In--Vicidial-blue?logo=github)](https://github.com/sihpl/Inbound-Filter-In-Vicidial)

---

## 🧐 Features

- ✅ Blocks inbound calls **not on your whitelist**
- ✅ Tracks call counts **per Caller ID (daily)**
- ✅ Limits each CLI to **5 calls per day**
- ✅ Fully **PHP-based AGI** implementation
- ✅ Easy integration via Asterisk `extensions.conf`
- ✅ Logs actions with lead tracking in `/tmp/whitelist.log`

---

## 📁 Files

| File                    | Description                         |
|-------------------------|-------------------------------------|
| `whitelist.php`         | Main AGI script for filtering calls |
| `phpagi.php`            | PHP AGI helper library              |
| `phpagi-asmanager.php`  | Asterisk Manager AGI helper         |

---

## 💪 Installation Guide

### 🔧 Step 1: Update Dialplan

Edit your `/etc/asterisk/extensions.conf`.

Replace this:
```asterisk
[trunkinbound]
exten => _X.,1,AGI(agi-DID_route.agi)
exten => _X.,n,Hangup()
```

With this:
```asterisk
[trunkinbound]
exten => _X.,1,AGI(/usr/src/agi-scripts/whitelist.php,${CALLERID(num)})
exten => _X.,n,AGI(agi-DID_route.agi)
exten => _X.,n,Hangup()
```

---

### 📁 Step 2: Create AGI Script Directory

```bash
cd /usr/src/
mkdir agi-scripts
```

---

### 👥 Step 3: Download AGI Scripts

```bash
cd /usr/src/agi-scripts

wget https://raw.githubusercontent.com/sihpl/Inbound-Filter-In-Vicidial/main/agi-scripts/phpagi-asmanager.php
wget https://raw.githubusercontent.com/sihpl/Inbound-Filter-In-Vicidial/main/agi-scripts/phpagi.php
wget https://raw.githubusercontent.com/sihpl/Inbound-Filter-In-Vicidial/main/agi-scripts/whitelist.php
```

---

### 🔐 Step 4: Set Proper Permissions

```bash
chmod -R 755 /usr/src/agi-scripts/*.php
```

---

### 🔄 Step 5: Reload Dialplan

```bash
asterisk -rx "dialplan reload"
```

---

## 🗃️ Step 6: Create MySQL Tables

Login to MySQL:
```bash
mysql -u root asterisk
```

Create the **call log table**:
```sql
CREATE TABLE cli_call_logs_all (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caller_id VARCHAR(20),
    call_date DATE,
    call_time DATETIME,
    call_status ENUM('ALLOWED', 'BLOCKED_WHITELIST', 'BLOCKED_LIMIT') NOT NULL,
    lead_id INT DEFAULT NULL
);
```

Create the **daily call count tracker**:
```sql
CREATE TABLE cli_call_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caller_id VARCHAR(20),
    call_date DATE,
    call_count INT DEFAULT 0
);
```

---

## 🧼 Step 7: Setup Auto-Cleanup with Crontab

Edit crontab:
```bash
crontab -e
```

Add the following lines to reset call limits and clean up old logs **daily at 1:00 AM**:

```bash
# Reset call limits daily
0 1 * * * mysql -u root asterisk -e "DELETE FROM cli_call_limits WHERE call_date < CURDATE();"

# Clean logs older than 30 days
0 1 * * * mysql -u root asterisk -e "DELETE FROM cli_call_logs_all WHERE call_date < CURDATE() - INTERVAL 30 DAY;"
```

---

## ✅ Usage & Behavior

| Scenario                                | Result                  |
|----------------------------------------|-------------------------|
| Caller **NOT** in whitelist            | ❌ Call blocked         |
| Caller in whitelist **< 5 times/day**  | ✅ Call allowed         |
| Caller in whitelist **> 5 times/day**  | ❌ Call blocked         |

### 🧪 Example Queries
```sql
-- Check all calls today
SELECT * FROM cli_call_logs_all WHERE call_date = CURDATE();

-- Check how many times a caller has called today
SELECT * FROM cli_call_limits WHERE caller_id = '1234567890' AND call_date = CURDATE();
```

---

## 🧪 Debugging Logs

Tail log for real-time activity:
```bash
tail -f /tmp/whitelist.log
```

---

## 🧪 Tested On

- ✅ VICIdial with **Asterisk 16**
- ✅ VICIdial with **Asterisk 11**
- ✅ ViciBox 11 / 12 with OpenSUSE Leap

---

## 🤝 Contributing

Pull requests and improvements are welcome. Fork & improve!

---

## 📜 License

Licensed under the [MIT License](https://opensource.org/licenses/MIT)

