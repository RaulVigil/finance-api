# FinanceApp – Backend API

Backend de **FinanceApp**, una **API REST** desarrollada con **CodeIgniter 4**, orientada a la gestión de finanzas personales.  
Implementa autenticación **JWT**, reglas de negocio financieras reales, manejo de deudas y transacciones con control de saldo en tiempo real.

> ⚠️ Proyecto en fase **MVP / Demo técnico**.

---

## 🚀 Stack tecnológico

### Core
- **PHP 8.1+**
- **CodeIgniter 4**
- **MySQL / MariaDB**
- **Arquitectura MVC**

### Seguridad & Auth
- **firebase/php-jwt** – generación y validación de JWT
- **Filtros personalizados** (Auth, CORS, Throttle)
- Hash de contraseñas (`password_hash`)

### Utilidades
- **PHPMailer** – envío de correos
- **Laminas Escaper** – seguridad en output
- **PSR-3 Logger**

---

## 🧠 Arquitectura general

- **Controllers**
  - Manejan endpoints y reglas de negocio
- **Models**
  - Acceso y persistencia de datos
- **Filters**
  - Autenticación JWT
  - CORS
  - Rate limiting
- **Helpers**
  - JWT
  - Email
  - Utilidades
- **Transacciones DB**
  - Garantizan consistencia financiera

---



