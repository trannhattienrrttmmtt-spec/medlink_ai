import os
import csv
import pandas as pd

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_DIR = os.path.join(BASE_DIR, "AMDGT", "data", "B-dataset")

DRUG_INFO_PATH = os.path.join(DATA_DIR, "DrugInformation.csv")
ASSOC_PATH = os.path.join(DATA_DIR, "DrugDiseaseAssociationNumber.csv")
ALLNODE_PATH = os.path.join(DATA_DIR, "Allnode.csv")
if not os.path.exists(ALLNODE_PATH):
    ALLNODE_PATH = os.path.join(DATA_DIR, "AllNode.csv")

OUT_PATH = os.path.join(os.path.dirname(__file__), "generator_dataset.csv")


def read_csv_rows(path):
    rows = []
    with open(path, "r", encoding="utf-8-sig") as f:
        reader = csv.reader(f)
        for row in reader:
            rows.append(row)
    return rows


def find_protein_start_index(rows):
    for i, row in enumerate(rows):
        if len(row) > 1:
            name = str(row[1]).strip().lower()
            if name.startswith("9606.ensp"):
                return i
    return -1


def load_drug_info():
    return pd.read_csv(DRUG_INFO_PATH)


def load_assoc():
    return pd.read_csv(ASSOC_PATH)


def get_disease_names_only():
    all_rows = read_csv_rows(ALLNODE_PATH)
    protein_start = find_protein_start_index(all_rows)

    if protein_start == -1:
        raise ValueError("Không tìm thấy mốc protein trong Allnode.csv")

    drug_df = load_drug_info()
    drug_count = len(drug_df)

    disease_rows = all_rows[drug_count:protein_start]

    disease_names = []
    for row in disease_rows:
        if len(row) >= 2:
            disease_name = str(row[1]).strip()
            if disease_name:
                disease_names.append(disease_name)

    return disease_names


def detect_columns(drug_df):
    drug_name_col = None
    smiles_col = None

    for c in drug_df.columns:
        c_low = c.lower()
        if drug_name_col is None and "name" in c_low:
            drug_name_col = c
        if smiles_col is None and "smiles" in c_low:
            smiles_col = c

    if drug_name_col is None:
        raise ValueError("Không tìm thấy cột tên thuốc trong DrugInformation.csv")
    if smiles_col is None:
        raise ValueError("Không tìm thấy cột smiles trong DrugInformation.csv")

    return drug_name_col, smiles_col


def main():
    disease_names = get_disease_names_only()
    drug_df = load_drug_info()
    assoc_df = load_assoc()

    drug_name_col, smiles_col = detect_columns(drug_df)

    assoc_drug_col = assoc_df.columns[0]
    assoc_disease_col = assoc_df.columns[1]

    rows = []

    for _, r in assoc_df.iterrows():
        try:
            drug_idx = int(r[assoc_drug_col])
            disease_idx = int(r[assoc_disease_col])
        except Exception:
            continue

        if not (0 <= drug_idx < len(drug_df)):
            continue
        if not (0 <= disease_idx < len(disease_names)):
            continue

        disease_name = str(disease_names[disease_idx]).strip().lower()
        drug_name = str(drug_df.iloc[drug_idx][drug_name_col]).strip()
        smiles = str(drug_df.iloc[drug_idx][smiles_col]).strip()

        if not smiles or smiles.lower() == "nan":
            continue

        rows.append({
            "disease_name": disease_name,
            "drug_name": drug_name,
            "smiles": smiles
        })

    out_df = pd.DataFrame(rows)

    # bỏ dòng trùng hoàn toàn
    out_df = out_df.drop_duplicates(subset=["disease_name", "drug_name", "smiles"])

    # chỉ giữ disease có ít nhất 2 SMILES để model học được phân bố
    disease_counts = out_df.groupby("disease_name")["smiles"].nunique()
    valid_diseases = disease_counts[disease_counts >= 2].index.tolist()
    out_df = out_df[out_df["disease_name"].isin(valid_diseases)].copy()

    # shuffle để train đỡ collapse
    out_df = out_df.sample(frac=1, random_state=42).reset_index(drop=True)

    out_df.to_csv(OUT_PATH, index=False, encoding="utf-8-sig")

    print(f"Đã tạo dataset: {OUT_PATH}")
    print(f"Số dòng: {len(out_df)}")
    print(f"Số disease hợp lệ: {out_df['disease_name'].nunique()}")
    print("Mẫu disease:", out_df["disease_name"].drop_duplicates().head(20).tolist())

    # in top disease có nhiều thuốc nhất
    top_counts = out_df.groupby("disease_name")["smiles"].nunique().sort_values(ascending=False).head(10)
    print("\nTop disease có nhiều SMILES:")
    print(top_counts)


if __name__ == "__main__":
    main()