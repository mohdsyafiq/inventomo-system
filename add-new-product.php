<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #4a5568;
            margin-top: 0;
            margin-bottom: 30px;
            font-size: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
        }
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .file-upload {
            position: relative;
            display: block;
            width: 100%;
            margin-bottom: 20px;
        }
        .file-upload-btn {
            width: 100%;
            padding: 10px;
            background-color: #e9ecef;
            color: #4a5568;
            text-align: center;
            border: 1px dashed #adb5bd;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 8px;
        }
        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .preview {
            width: 100%;
            height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .submit-btn {
            background-color: #6366f1;
            color: white;
            border: none;
            padding: 12px 20px;
            width: 100%;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .submit-btn:hover {
            background-color: #4f46e5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add New Product</h1>
        <form id="addProductForm">
            <div class="form-group">
                <label for="productName">Product Name</label>
                <input type="text" id="productName" name="productName" required>
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>
                    <option value="Electronic">Electronic</option>
                    <option value="Accessories">Accessories</option>
                    <option value="Furniture">Furniture</option>
                    <option value="Kitchen">Kitchen</option>
                    <option value="Office">Office</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="price">Price (RM)</label>
                <input type="number" id="price" name="price" min="0" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label>Product Image</label>
                <div class="preview">
                    <img src="/api/placeholder/200/150" alt="Product preview" id="imagePreview">
                </div>
                <div class="file-upload">
                    <div class="file-upload-btn">
                        <i class="fa fa-cloud-upload"></i> Choose Image
                    </div>
                    <input type="file" class="file-upload-input" id="productImage" accept="image/*" onchange="previewImage(this)">
                </div>
            </div>
            
            <button type="submit" class="submit-btn">Add Product</button>
        </form>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }

        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get current date in DD-MM-YYYY format
            const today = new Date();
            const day = String(today.getDate()).padStart(2, '0');
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const year = today.getFullYear();
            const date = `${day}-${month}-${year}`;
            
            // Get form values
            const product = document.getElementById('productName').value;
            const category = document.getElementById('category').value;
            const stock = document.getElementById('stock').value;
            const price = parseFloat(document.getElementById('price').value).toFixed(2);
            
            // In a real application, you would send this data to a server
            alert(`Product added successfully!\n\nProduct: ${product}\nCategory: ${category}\nStock: ${stock}\nPrice: RM${price}\nDate: ${date}`);
            
            // Reset form
            this.reset();
            document.getElementById('imagePreview').src = "/api/placeholder/200/150";
        });
    </script>
</body>
</html>