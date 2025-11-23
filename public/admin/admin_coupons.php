<h2>Add / Edit Coupon</h2>
<form method="POST" action="save_coupon.php">
  <label>Coupon Code:</label>
  <input type="text" name="code" required><br>

  <label>Discount Type:</label>
  <select name="discount_type">
    <option value="fixed">Fixed Amount</option>
    <option value="percent">Percentage</option>
  </select><br>

  <label>Discount Value:</label>
  <input type="number" step="0.01" name="discount_value" required><br>

  <label>Start Date:</label>
  <input type="date" name="start_date" required><br>

  <label>End Date:</label>
  <input type="date" name="end_date" required><br>

  <label>Max Usage:</label>
  <input type="number" name="max_usage" value="1"><br>

  <label>Status:</label>
  <select name="status">
    <option value="active">Active</option>
    <option value="inactive">Inactive</option>
  </select><br>

  <button type="submit">Save Coupon</button>
</form>
