<input type="hidden" name="action" value="add">

<div class="mb-3">
    <label for="custom_id" class="form-label">ID Supplier (Opsional)</label>
    <input type="number" class="form-control" id="custom_id" name="custom_id" placeholder="Kosongkan untuk ID otomatis">
    <div class="form-text">Isi hanya jika ingin menggunakan ID spesifik (misal: 70)</div>
</div>

<div class="mb-3">
    <label for="kode_supplier" class="form-label">Kode Supplier*</label>
    <input type="text" class="form-control" id="kode_supplier" name="kode_supplier" required>
</div>

<div class="mb-3">
    <label for="nama_supplier" class="form-label">Nama Supplier*</label>
    <input type="text" class="form-control" id="nama_supplier" name="nama_supplier" required>
</div>

<div class="mb-3">
    <label for="alamat" class="form-label">Alamat</label>
    <textarea class="form-control" id="alamat" name="alamat" rows="3"></textarea>
</div>

<div class="mb-3">
    <label for="telepon" class="form-label">Telepon</label>
    <input type="text" class="form-control" id="telepon" name="telepon">
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email" class="form-control" id="email" name="email">
</div>

<div class="mb-3">
    <label for="no_rekening" class="form-label">No Rekening</label>
    <input type="text" class="form-control" id="no_rekening" name="no_rekening">
</div>

<div class="mb-3">
    <label for="terms_of_payment" class="form-label">Terms of Payment (hari)</label>
    <input type="number" class="form-control" id="terms_of_payment" name="terms_of_payment" value="0">
</div>

<div class="mb-3">
    <label for="gambar" class="form-label">Gambar Supplier</label>
    <input type="file" class="form-control" id="gambar" name="gambar" accept="image/*">
    <div class="form-text">Format: JPG, PNG, GIF | Maksimal: 2MB</div>
</div>