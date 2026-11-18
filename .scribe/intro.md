# Introduction



<aside>
    <strong>Base URL</strong>: <code>https://vendingmachine-assessment.onrender.com</code>
</aside>

REST API for the vending machine (v1). Get a token from `POST /api/v1/auth/login`, then send it as `Authorization: Bearer <token>`.

Demo accounts: `admin@vending.test` / `password` (can manage products) and `user@vending.test` / `password` (can buy). Prices are decimal strings with 3 decimals, e.g. `"6.885"`.

