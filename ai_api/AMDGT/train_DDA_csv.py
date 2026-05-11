import os
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


device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')


def make_fold_dirs(args):
    for i in range(args.k_fold):
        os.makedirs(os.path.join(args.data_dir, 'fold', str(i)), exist_ok=True)


def save_result_csv(args, fold_rows):
    os.makedirs(args.result_dir, exist_ok=True)

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    csv_path = os.path.join(
        args.result_dir,
        f"10_fold_results_{args.dataset}_{timestamp}.csv"
    )

    metric_cols = [
        "AUC",
        "AUPR",
        "Accuracy",
        "Precision",
        "Recall",
        "F1-score",
        "Mcc"
    ]

    df = pd.DataFrame(fold_rows)

    mean_row = {"Fold": "Mean", "Best_Epoch": ""}
    std_row = {"Fold": "Std", "Best_Epoch": ""}

    for col in metric_cols:
        mean_row[col] = df[col].mean()
        std_row[col] = df[col].std(ddof=0)

    df = pd.concat(
        [
            df,
            pd.DataFrame([mean_row]),
            pd.DataFrame([std_row])
        ],
        ignore_index=True
    )

    df.to_csv(csv_path, index=False, encoding="utf-8-sig")

    print("\n========== FINAL RESULT ==========")
    print(df)
    print("\nSaved CSV:", csv_path)

    return csv_path


if __name__ == '__main__':

    parser = argparse.ArgumentParser()

    parser.add_argument('--k_fold', type=int, default=10)
    parser.add_argument('--epochs', type=int, default=1000)
    parser.add_argument('--lr', type=float, default=1e-4)
    parser.add_argument('--weight_decay', type=float, default=1e-3)
    parser.add_argument('--random_seed', type=int, default=1234)
    parser.add_argument('--neighbor', type=int, default=20)
    parser.add_argument('--negative_rate', type=float, default=1.0)
    parser.add_argument('--dataset', default='C-dataset')
    parser.add_argument('--dropout', default=0.2, type=float)

    parser.add_argument('--gt_layer', default=2, type=int)
    parser.add_argument('--gt_head', default=2, type=int)
    parser.add_argument('--gt_out_dim', default=200, type=int)

    parser.add_argument('--hgt_layer', default=2, type=int)
    parser.add_argument('--hgt_head', default=8, type=int)
    parser.add_argument('--hgt_in_dim', default=64, type=int)
    parser.add_argument('--hgt_head_dim', default=25, type=int)
    parser.add_argument('--hgt_out_dim', default=200, type=int)

    parser.add_argument('--tr_layer', default=2, type=int)
    parser.add_argument('--tr_head', default=4, type=int)

    args = parser.parse_args()

    args.data_dir = 'data/' + args.dataset + '/'
    args.result_dir = 'Result/' + args.dataset + '/AMNTDDA/'

    os.makedirs(args.result_dir, exist_ok=True)
    make_fold_dirs(args)

    print("=" * 70)
    print("Device       :", device)
    print("Dataset      :", args.dataset)
    print("Epochs       :", args.epochs)
    print("K-Fold       :", args.k_fold)
    print("Neighbor k   :", args.neighbor)
    print("GT out dim d :", args.gt_out_dim)
    print("HGT head dim :", args.hgt_head_dim)
    print("LR           :", args.lr)
    print("Weight Decay :", args.weight_decay)
    print("Dropout      :", args.dropout)
    print("Result Dir   :", args.result_dir)
    print("=" * 70)

    data = get_data(args)

    args.drug_number = data['drug_number']
    args.disease_number = data['disease_number']
    args.protein_number = data['protein_number']

    data = data_processing(data, args)
    data = k_fold(data, args)

    drdr_graph, didi_graph, data = dgl_similarity_graph(data, args)

    drdr_graph = drdr_graph.to(device)
    didi_graph = didi_graph.to(device)

    drug_feature = torch.FloatTensor(data['drugfeature']).to(device)
    disease_feature = torch.FloatTensor(data['diseasefeature']).to(device)
    protein_feature = torch.FloatTensor(data['proteinfeature']).to(device)

    start = timeit.default_timer()
    cross_entropy = nn.CrossEntropyLoss()

    print('Dataset:', args.dataset)
    print('Epoch\t\tTime\t\tAUC\t\tAUPR\t\tAccuracy\t\tPrecision\t\tRecall\t\tF1-score\t\tMcc')

    fold_rows = []

    for i in range(args.k_fold):

        print("\n" + "=" * 70)
        print('fold:', i)
        print('Epoch\t\tTime\t\tAUC\t\tAUPR\t\tAccuracy\t\tPrecision\t\tRecall\t\tF1-score\t\tMcc')

        model = AMNTDDA(args).to(device)

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

        X_train = torch.LongTensor(data['X_train'][i]).to(device)
        Y_train = torch.LongTensor(data['Y_train'][i]).to(device)

        X_test = torch.LongTensor(data['X_test'][i]).to(device)
        Y_test = data['Y_test'][i].flatten()

        drdipr_graph, data = dgl_heterograph(
            data,
            data['X_train'][i],
            args
        )

        drdipr_graph = drdipr_graph.to(device)

        for epoch in range(args.epochs):
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

            train_loss = cross_entropy(
                train_score,
                torch.flatten(Y_train)
            )

            optimizer.zero_grad()
            train_loss.backward()
            optimizer.step()

            with torch.no_grad():
                model.eval()

                _, test_score = model(
                    drdr_graph,
                    didi_graph,
                    drdipr_graph,
                    drug_feature,
                    disease_feature,
                    protein_feature,
                    X_test
                )

            test_prob = fn.softmax(test_score, dim=-1)
            test_pred = torch.argmax(test_score, dim=-1)

            test_prob = test_prob[:, 1].detach().cpu().numpy()
            test_pred = test_pred.detach().cpu().numpy()

            AUC, AUPR, accuracy, precision, recall, f1, mcc = get_metric(
                Y_test,
                test_pred,
                test_prob
            )

            end = timeit.default_timer()
            run_time = end - start

            show = [
                epoch + 1,
                round(run_time, 2),
                round(AUC, 5),
                round(AUPR, 5),
                round(accuracy, 5),
                round(precision, 5),
                round(recall, 5),
                round(f1, 5),
                round(mcc, 5)
            ]

            print('\t\t'.join(map(str, show)))

            if AUC > best_auc:
                best_epoch = epoch + 1
                best_auc = AUC
                best_aupr = AUPR
                best_accuracy = accuracy
                best_precision = precision
                best_recall = recall
                best_f1 = f1
                best_mcc = mcc

                print(
                    'AUC improved at epoch',
                    best_epoch,
                    '; best_auc:',
                    best_auc
                )

                # Lưu model tốt nhất của fold hiện tại
                model_path_0 = os.path.join(args.result_dir, f"best_fold_{i}.pt")
                model_path_1 = os.path.join(args.result_dir, f"best_fold_{i + 1}.pt")

                torch.save(model.state_dict(), model_path_0)
                torch.save(model.state_dict(), model_path_1)

                print("Saved model:", model_path_0)
                print("Saved model:", model_path_1)

        fold_rows.append({
            "Fold": f"Fold {i}",
            "Best_Epoch": best_epoch,
            "AUC": best_auc,
            "AUPR": best_aupr,
            "Accuracy": best_accuracy,
            "Precision": best_precision,
            "Recall": best_recall,
            "F1-score": best_f1,
            "Mcc": best_mcc
        })

    save_result_csv(args, fold_rows)