# MedLink AI — Drug-Disease Association Prediction

## Yêu cầu
- Windows 10/11
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL)
- Python 3.9+ với CUDA (nếu có GPU NVIDIA)

## Cài đặt (1 lần)

1. Cài XAMPP → mặc định `C:\xampp`
2. Copy folder `medlink_ai` vào `C:\xampp\htdocs\`
3. Cài Python dependencies:
```bash
cd C:\xampp\htdocs\medlink_ai\ai_api
python -m venv .venv_real
.venv_real\Scripts\activate
pip install -r requirements.txt
```

## Chạy

**Bấm đúp file `START.bat`** — tự động:
- Start Apache + MySQL
- Import database (lần đầu)
- Start Flask AI API
- Mở browser

## Đăng nhập
- Username: `admin`
- Password: `123456`

## Tính năng
- Dự đoán thuốc → bệnh (và ngược lại)
- So sánh 2 model: Cải tiến vs Gốc AMDGT
- Mạng liên kết Drug-Disease (graph visualization)
- Sinh thuốc mới từ triệu chứng
- Lịch sử tra cứu
- Dark mode

## Datasets
- B-dataset: 269 thuốc, 598 bệnh, 1021 proteins
- C-dataset: 663 thuốc, 409 bệnh, 993 proteins
- F-dataset: 592 thuốc, 313 bệnh, 2741 proteins
