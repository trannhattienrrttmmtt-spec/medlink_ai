# -*- coding: utf-8 -*-

import os
import csv
import timeit
import argparse
from datetime import datetime

import numpy as np
import pandas as pd
import torch
import torch.optim as optim
import torch.nn as nn
import torch.nn.functional as fn

from data_preprocess import *
from model.AMNTDDA import AMNTDDA
from metric import *


# =========================================================
# DEVICE
# =========================================================

device = torch.device("cuda" if torch.cuda.is_available() else "cpu")


# =========================================================
# HELPER
# =========================================================

def ensure_dir(path):
    os.makedirs(path, exist_ok=True)


def set_seed(seed):
    np.random.seed(seed)
    torch.manual_seed(seed)

    if torch.cuda.is_available():
        torch.cuda.manual_seed(seed)
        torch.cuda.manual_seed_all(seed)

    torch.backends.cudnn.deterministic = False
    torch.backends.cudnn.benchmark = True


def write_csv(path, rows, fieldnames):
    ensure_dir(os.path.dirname(path))

    with open(path, "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()

        for row in rows:
            writer.writerow(row)


def append_text(path, text):
    ensure_dir(os.path.dirname(path))

    with open(path, "a", encoding="utf-8") as f:
        f.write(text + "\n")


def save_best_model(model, result_dir, fold_index):
    """
    Lưu 2 kiểu tên để web/predictor load kiểu nào cũng được:

    Kiểu 0-index:
    best_fold_0.pt ... best_fold_9.pt

    Kiểu 1-index:
    best_fold_1.pt ... best_fold_10.pt
    """

    ensure_dir(result_dir)

    path_0_index = os.path.join(result_dir, f"best_fold_{fold_index}.pt")
    path_1_index = os.path.join(result_dir, f"best_fold_{fold_index + 1}.pt")

    state_dict = model.state_dict()

    torch.save(state_dict, path_0_index)
    torch.save(state_dict, path_1_index)

    return path_0_index, path_1_index


# =========================================================
# MAIN
# =========================================================

if __name__ == "__main__":
    parser = argparse.ArgumentParser()

    parser.add_argument("--k_fold", type=int, default=10, help="k-fold cross validation")
    parser.add_argument("--epochs", type=int, default=1000, help="number of epochs to train")
    parser.add_argument("--lr", type=float, default=1e-4, help="learning rate")
    parser.add_argument("--weight_decay", type=float, default=1e-3, help="weight_decay")
    parser.add_argument("--random_seed", type=int, default=1234, help="random seed")
    parser.add_argument("--neighbor", type=int, default=20, help="neighbor")
    parser.add_argument("--negative_rate", type=float, default=1.0, help="negative_rate")
    parser.add_argument("--dataset", default="C-dataset", help="dataset")
    parser.add_argument("--dropout", default=0.2, type=float, help="dropout")

    parser.add_argument("--gt_layer", default=2, type=int, help="graph transformer layer")
    parser.add_argument("--gt_head", default=2, type=int, help="graph transformer head")
    parser.add_argument("--gt_out_dim", default=200, type=int, help="graph transformer output dimension")

    parser.add_argument("--hgt_layer", default=2, type=int, help="heterogeneous graph transformer layer")
    parser.add_argument("--hgt_head", default=8, type=int, help="heterogeneous graph transformer head")
    parser.add_argument("--hgt_in_dim", default=64, type=int, help="heterogeneous graph transformer input dimension")
    parser.add_argument("--hgt_head_dim", default=25, type=int, help="heterogeneous graph transformer head dimension")
    parser.add_argument("--hgt_out_dim", default=200, type=int, help="heterogeneous graph transformer output dimension")

    parser.add_argument("--tr_layer", default=2, type=int, help="transformer layer")
    parser.add_argument("--tr_head", default=4, type=int, help="transformer head")

    args = parser.parse_args()

    set_seed(args.random_seed)

    args.data_dir = "data/" + args.dataset + "/"
    args.result_dir = "Result/" + args.dataset + "/AMNTDDA/"

    ensure_dir(args.result_dir)

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")

    epoch_csv_path = os.path.join(
        args.result_dir,
        f"epoch_log_{args.dataset}_{timestamp}.csv"
    )

    fold_csv_path = os.path.join(
        args.result_dir,
        f"10_fold_results_{args.dataset}_{timestamp}.csv"
    )

    summary_txt_path = os.path.join(
        args.result_dir,
        f"summary_{args.dataset}_{timestamp}.txt"
    )

    print("=" * 80)
    print("AMDGT ORIGINAL TRAIN WITH BEST FOLD + CSV")
    print("Dataset        :", args.dataset)
    print("Device         :", device)
    print("CUDA available :", torch.cuda.is_available())
    if torch.cuda.is_available():
        print("GPU            :", torch.cuda.get_device_name(0))
    print("Result dir     :", args.result_dir)
    print("Epoch CSV      :", epoch_csv_path)
    print("Fold CSV       :", fold_csv_path)
    print("=" * 80)

    append_text(summary_txt_path, "=" * 80)
    append_text(summary_txt_path, "AMDGT ORIGINAL TRAIN WITH BEST FOLD + CSV")
    append_text(summary_txt_path, f"Dataset        : {args.dataset}")
    append_text(summary_txt_path, f"Device         : {device}")
    append_text(summary_txt_path, f"CUDA available : {torch.cuda.is_available()}")
    if torch.cuda.is_available():
        append_text(summary_txt_path, f"GPU            : {torch.cuda.get_device_name(0)}")
    append_text(summary_txt_path, f"Result dir     : {args.result_dir}")
    append_text(summary_txt_path, "=" * 80)

    # =========================================================
    # LOAD DATA
    # =========================================================

    data = get_data(args)

    args.drug_number = data["drug_number"]
    args.disease_number = data["disease_number"]
    args.protein_number = data["protein_number"]

    data = data_processing(data, args)
    data = k_fold(data, args)

    drdr_graph, didi_graph, data = dgl_similarity_graph(data, args)

    drdr_graph = drdr_graph.to(device)
    didi_graph = didi_graph.to(device)

    drug_feature = torch.FloatTensor(data["drugfeature"]).to(device)
    disease_feature = torch.FloatTensor(data["diseasefeature"]).to(device)
    protein_feature = torch.FloatTensor(data["proteinfeature"]).to(device)

    all_sample = torch.tensor(data["all_drdi"]).long()

    start = timeit.default_timer()
    cross_entropy = nn.CrossEntropyLoss()

    metric_header = (
        "Epoch\t\tTime\t\tAUC\t\tAUPR\t\tAccuracy\t\t"
        "Precision\t\tRecall\t\tF1-score\t\tMcc"
    )

    AUCs = []
    AUPRs = []

    epoch_rows = []
    fold_rows = []

    print("Dataset:", args.dataset)

    # =========================================================
    # TRAIN K-FOLD
    # =========================================================

    for i in range(args.k_fold):
        print("fold:", i)
        print(metric_header)

        append_text(summary_txt_path, "")
        append_text(summary_txt_path, f"Fold {i}")
        append_text(summary_txt_path, metric_header)

        model = AMNTDDA(args)
        model = model.to(device)

        optimizer = optim.Adam(
            model.parameters(),
            weight_decay=args.weight_decay,
            lr=args.lr
        )

        best_epoch = 0
        best_auc = 0
        best_aupr = 0
        best_accuracy = 0
        best_precision = 0
        best_recall = 0
        best_f1 = 0
        best_mcc = 0
        best_model_path_0 = ""
        best_model_path_1 = ""

        X_train = torch.LongTensor(data["X_train"][i]).to(device)
        Y_train = torch.LongTensor(data["Y_train"][i]).to(device)

        X_test = torch.LongTensor(data["X_test"][i]).to(device)
        Y_test = data["Y_test"][i].flatten()

        drdipr_graph, data = dgl_heterograph(data, data["X_train"][i], args)
        drdipr_graph = drdipr_graph.to(device)

        for epoch in range(args.epochs):
            # =========================
            # TRAIN
            # =========================

            model.train()

            _, train_score = model(
                drdr_graph,
                didi_graph,
                drdipr_graph,
                drug_feature,
                disease_feature,
                protein_feature,
                X_train
            )

            train_loss = cross_entropy(train_score, torch.flatten(Y_train))

            optimizer.zero_grad()
            train_loss.backward()
            optimizer.step()

            # =========================
            # EVAL
            # =========================

            with torch.no_grad():
                model.eval()

                dr_representation, test_score = model(
                    drdr_graph,
                    didi_graph,
                    drdipr_graph,
                    drug_feature,
                    disease_feature,
                    protein_feature,
                    X_test
                )

                test_prob = fn.softmax(test_score, dim=-1)
                test_score = torch.argmax(test_score, dim=-1)
                test_prob = test_prob[:, 1]

                test_prob = test_prob.cpu().numpy()
                test_score = test_score.cpu().numpy()

                AUC, AUPR, accuracy, precision, recall, f1, mcc = get_metric(
                    Y_test,
                    test_score,
                    test_prob
                )

                end = timeit.default_timer()
                used_time = end - start

                show = [
                    epoch + 1,
                    round(used_time, 2),
                    round(AUC, 5),
                    round(AUPR, 5),
                    round(accuracy, 5),
                    round(precision, 5),
                    round(recall, 5),
                    round(f1, 5),
                    round(mcc, 5),
                ]

                print("\t\t".join(map(str, show)))

                append_text(
                    summary_txt_path,
                    "\t\t".join(map(str, show))
                )

                epoch_rows.append(
                    {
                        "dataset": args.dataset,
                        "fold": i,
                        "epoch": epoch + 1,
                        "time": round(used_time, 4),
                        "train_loss": float(train_loss.detach().cpu().item()),
                        "auc": float(AUC),
                        "aupr": float(AUPR),
                        "accuracy": float(accuracy),
                        "precision": float(precision),
                        "recall": float(recall),
                        "f1": float(f1),
                        "mcc": float(mcc),
                        "is_best": 1 if AUC > best_auc else 0,
                    }
                )

                # =========================
                # SAVE BEST MODEL
                # =========================

                if AUC > best_auc:
                    best_epoch = epoch + 1
                    best_auc = AUC
                    best_aupr = AUPR
                    best_accuracy = accuracy
                    best_precision = precision
                    best_recall = recall
                    best_f1 = f1
                    best_mcc = mcc

                    best_model_path_0, best_model_path_1 = save_best_model(
                        model,
                        args.result_dir,
                        i
                    )

                    msg = (
                        f"AUC improved at epoch {best_epoch}; "
                        f"best_auc: {best_auc}; "
                        f"saved: {best_model_path_0} and {best_model_path_1}"
                    )

                    print(msg)
                    append_text(summary_txt_path, msg)

            # Ghi epoch csv định kỳ để nếu tắt máy vẫn còn log
            if (epoch + 1) % 10 == 0:
                write_csv(
                    epoch_csv_path,
                    epoch_rows,
                    [
                        "dataset",
                        "fold",
                        "epoch",
                        "time",
                        "train_loss",
                        "auc",
                        "aupr",
                        "accuracy",
                        "precision",
                        "recall",
                        "f1",
                        "mcc",
                        "is_best",
                    ]
                )

        # =====================================================
        # FOLD SUMMARY
        # =====================================================

        AUCs.append(best_auc)
        AUPRs.append(best_aupr)

        fold_row = {
            "dataset": args.dataset,
            "fold": i,
            "best_epoch": best_epoch,
            "best_auc": float(best_auc),
            "best_aupr": float(best_aupr),
            "best_accuracy": float(best_accuracy),
            "best_precision": float(best_precision),
            "best_recall": float(best_recall),
            "best_f1": float(best_f1),
            "best_mcc": float(best_mcc),
            "best_model_path_0_index": best_model_path_0,
            "best_model_path_1_index": best_model_path_1,
        }

        fold_rows.append(fold_row)

        write_csv(
            fold_csv_path,
            fold_rows,
            [
                "dataset",
                "fold",
                "best_epoch",
                "best_auc",
                "best_aupr",
                "best_accuracy",
                "best_precision",
                "best_recall",
                "best_f1",
                "best_mcc",
                "best_model_path_0_index",
                "best_model_path_1_index",
            ]
        )

        write_csv(
            epoch_csv_path,
            epoch_rows,
            [
                "dataset",
                "fold",
                "epoch",
                "time",
                "train_loss",
                "auc",
                "aupr",
                "accuracy",
                "precision",
                "recall",
                "f1",
                "mcc",
                "is_best",
            ]
        )

        print("Fold", i, "best_auc:", best_auc, "best_aupr:", best_aupr)
        append_text(summary_txt_path, f"Fold {i} best_auc: {best_auc}, best_aupr: {best_aupr}")

    # =========================================================
    # FINAL SUMMARY
    # =========================================================

    print("AUC:", AUCs)

    AUC_mean = np.mean(AUCs)
    AUC_std = np.std(AUCs)

    print("Mean AUC:", AUC_mean, "(", AUC_std, ")")

    print("AUPR:", AUPRs)

    AUPR_mean = np.mean(AUPRs)
    AUPR_std = np.std(AUPRs)

    print("Mean AUPR:", AUPR_mean, "(", AUPR_std, ")")

    append_text(summary_txt_path, "")
    append_text(summary_txt_path, "=" * 80)
    append_text(summary_txt_path, f"AUC: {AUCs}")
    append_text(summary_txt_path, f"Mean AUC: {AUC_mean} ({AUC_std})")
    append_text(summary_txt_path, f"AUPR: {AUPRs}")
    append_text(summary_txt_path, f"Mean AUPR: {AUPR_mean} ({AUPR_std})")
    append_text(summary_txt_path, f"Epoch CSV: {epoch_csv_path}")
    append_text(summary_txt_path, f"Fold CSV: {fold_csv_path}")
    append_text(summary_txt_path, "=" * 80)

    print("Saved epoch CSV:", epoch_csv_path)
    print("Saved fold CSV :", fold_csv_path)
    print("Saved summary  :", summary_txt_path)