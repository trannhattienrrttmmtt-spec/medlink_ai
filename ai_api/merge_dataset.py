import pandas as pd

def merge_all():
    B = pd.read_csv("B-dataset/Allnode.csv")
    C = pd.read_csv("C-dataset/Allnode.csv")
    F = pd.read_csv("F-dataset/Allnode.csv")

    merged = pd.concat([B, C, F]).drop_duplicates()

    merged.to_csv("merged_dataset.csv", index=False)