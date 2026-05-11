@echo off
chcp 65001 >nul
title Train 3 Dataset MedLink AI AMDGT

cd /d C:\xampp\htdocs\medlink_ai\ai_api\AMDGT

echo ==========================================
echo ACTIVATE VENV
echo ==========================================

if exist venv_original\Scripts\activate (
    call venv_original\Scripts\activate
) else if exist venv\Scripts\activate (
    call venv\Scripts\activate
) else (
    echo Khong thay venv, se dung Python he thong
)

echo Python dang dung:
where python
python --version

echo ==========================================
echo XOA CACHE
echo ==========================================
rmdir /s /q __pycache__ 2>nul
rmdir /s /q model\__pycache__ 2>nul
rmdir /s /q data\B-dataset\fold 2>nul
rmdir /s /q data\C-dataset\fold 2>nul
rmdir /s /q data\F-dataset\fold 2>nul

echo.
echo ==========================================
echo TRAIN B-DATASET - PAPER CONFIG
echo k=3, d=512
echo ==========================================
python -u train_DDA_csv.py ^
--dataset B-dataset ^
--k_fold 10 ^
--epochs 1000 ^
--neighbor 3 ^
--gt_out_dim 512 ^
--hgt_out_dim 512 ^
--hgt_head 8 ^
--hgt_head_dim 64 ^
--lr 0.0001 ^
--weight_decay 0.001 ^
--dropout 0.2 ^
--negative_rate 1.0

echo.
echo ==========================================
echo TRAIN C-DATASET - PAPER CONFIG
echo k=5, d=256
echo ==========================================
python -u train_DDA_csv.py ^
--dataset C-dataset ^
--k_fold 10 ^
--epochs 1000 ^
--neighbor 5 ^
--gt_out_dim 256 ^
--hgt_out_dim 256 ^
--hgt_head 8 ^
--hgt_head_dim 32 ^
--lr 0.0001 ^
--weight_decay 0.001 ^
--dropout 0.2 ^
--negative_rate 1.0

echo.
echo ==========================================
echo TRAIN F-DATASET - PAPER CONFIG
echo k=5, d=256
echo ==========================================
python -u train_DDA_csv.py ^
--dataset F-dataset ^
--k_fold 10 ^
--epochs 1000 ^
--neighbor 5 ^
--gt_out_dim 256 ^
--hgt_out_dim 256 ^
--hgt_head 8 ^
--hgt_head_dim 32 ^
--lr 0.0001 ^
--weight_decay 0.001 ^
--dropout 0.2 ^
--negative_rate 1.0

echo.
echo ==========================================
echo DONE 3 DATASET
echo Ket qua nam trong:
echo C:\xampp\htdocs\medlink_ai\ai_api\Result\B-dataset\AMNTDDA
echo C:\xampp\htdocs\medlink_ai\ai_api\Result\C-dataset\AMNTDDA
echo C:\xampp\htdocs\medlink_ai\ai_api\Result\F-dataset\AMNTDDA
echo ==========================================

pause