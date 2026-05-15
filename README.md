# Expense Tracker API

A simple PHP project built to practice API development and backend logic.

This app allows users to add expenses, get saved expenses, and calculate total spending. The goal of this project was to better understand GET and POST requests in PHP and how APIs work.

## Features

- Add expenses using POST request
- Get saved expenses using GET request
- Calculate total spending
- Input validation
- Prevent empty fields
- Prevent negative amounts
- Category filtering (optional)

## Tech Used

- PHP
- JSON (for storing expenses and mysql)
- HTML (for testing requests)

## API Endpoints

### Add Expense

```http
POST /add-expense.php
```

Example:

```json
{
  "title": "Transport",
  "amount": 3000,
  "category": "Travel"
}
```

---

### Get Expenses

```http
GET /get-expenses.php
```

Returns all saved expenses.

---

### Calculate Total Spending

```http
GET /calculate-total.php
```

Returns the total amount spent.

## Why I Built This

I built this project to practice PHP backend development, API handling, form validation, and working with GET and POST requests.

It also helped me understand how data can be stored and processed before moving into database-based projects.

## How to Run

1. Clone this repository

```bash
git clone your-repository-link
```

2. Move the project folder into:

```bash
C:\xampp\htdocs
```

3. Start Apache in XAMPP

4. Open in browser:

```bash
http://localhost/expense-tracker
```

## Future Improvements

- Save expenses in MySQL
- User authentication
- Expense categories dashboard
- Monthly spending summary

  ## Live Project at
  https://expenses-tracker-arf9.onrender.com

## Author

Umar Farouk
