# -*- coding: utf-8 -*-
"""
MedLink AI Flask API

Routes:
- GET  /health
- GET  /drugs
- GET  /diseases
- GET  /drug_options
- GET  /disease_options
- POST /predict
- POST /predict_compare
- POST /generate_drug
- GET/POST /reload
"""

import sys
import random
import traceback
from pathlib import Path

import torch
from flask import Flask, request, jsonify
from flask_cors import CORS


# =========================================================
# CONFIG
# =========================================================

BASE_DIR = Path(__file__).resolve().parent
AMDGT_DIR = BASE_DIR / "AMDGT"
AMDGT_ORIGINAL_DIR = BASE_DIR / "AMDGT_ORIGINAL"
DATA_DIR = AMDGT_DIR / "data"

DATASETS = ["B-dataset", "C-dataset", "F-dataset"]

DEFAULT_DATASET = "B-dataset"
DEFAULT_TOP_K = 10

DEVICE = torch.device("cuda" if torch.cuda.is_available() else "cpu")


# =========================================================
# FLASK
# =========================================================

app = Flask(__name__)
CORS(app)


print("=" * 70)
print("MEDLINK AI API STARTING")
print("BASE_DIR:", BASE_DIR)
print("PYTHON:", sys.executable)
print("TORCH:", torch.__version__)
print("CUDA AVAILABLE:", torch.cuda.is_available())
print("TORCH CUDA:", torch.version.cuda)
print("DEVICE:", DEVICE)
if torch.cuda.is_available():
    print("GPU:", torch.cuda.get_device_name(0))
print("=" * 70)


# =========================================================
# HELPER
# =========================================================

def safe_int(value, default=10, min_value=1, max_value=100):
    try:
        value = int(value)
        if value < min_value:
            return min_value
        if value > max_value:
            return max_value
        return value
    except Exception:
        return default


def normalize_text(s):
    if s is None:
        return ""
    return str(s).strip()


def normalize_key(s):
    return normalize_text(s).lower()


def clean_dataset(dataset):
    dataset = normalize_text(dataset)
    if dataset not in DATASETS:
        return DEFAULT_DATASET
    return dataset


def read_csv_rows(path):
    import csv

    rows = []

    if not path or not Path(path).exists():
        return rows

    encodings = ["utf-8-sig", "utf-8", "latin1"]

    for enc in encodings:
        try:
            with open(path, "r", encoding=enc, newline="") as f:
                reader = csv.DictReader(f)
                for row in reader:
                    rows.append(row)
            return rows
        except Exception:
            rows = []

    return rows


def find_file_case_insensitive(folder, names):
    folder = Path(folder)

    if not folder.exists():
        return None

    files = {}
    for p in folder.iterdir():
        if p.is_file():
            files[p.name.lower()] = p

    for name in names:
        found = files.get(name.lower())
        if found:
            return found

    return None


def dataset_path(dataset):
    dataset = clean_dataset(dataset)
    return DATA_DIR / dataset


def get_drug_info_file(dataset):
    dpath = dataset_path(dataset)

    return find_file_case_insensitive(
        dpath,
        [
            "DrugInformation.csv",
            "drugInformation.csv",
            "drug_information.csv",
            "Drug_Information.csv",
        ],
    )


def get_allnode_file(dataset):
    dpath = dataset_path(dataset)

    return find_file_case_insensitive(
        dpath,
        [
            "Allnode.csv",
            "AllNode.csv",
            "allnode.csv",
            "all_node.csv",
        ],
    )


# =========================================================
# LOAD DRUG / DISEASE OPTIONS
# =========================================================

def load_drugs(dataset=DEFAULT_DATASET):
    dataset = clean_dataset(dataset)

    file_path = get_drug_info_file(dataset)
    rows = read_csv_rows(file_path)

    drugs = []
    seen = set()

    for row in rows:
        keys = {str(k).lower(): k for k in row.keys()}

        name = ""
        drug_id = ""
        smiles = ""

        for c in [
            "drugname",
            "drug_name",
            "name",
            "drug",
            "drugbankid",
            "drugbank_id",
        ]:
            if c in keys and normalize_text(row.get(keys[c])):
                name = normalize_text(row.get(keys[c]))
                break

        for c in [
            "drugbankid",
            "drugbank_id",
            "drug_id",
            "id",
        ]:
            if c in keys and normalize_text(row.get(keys[c])):
                drug_id = normalize_text(row.get(keys[c]))
                break

        for c in [
            "smiles",
            "canonical_smiles",
            "canonicalsmiles",
        ]:
            if c in keys and normalize_text(row.get(keys[c])):
                smiles = normalize_text(row.get(keys[c]))
                break

        if not name and drug_id:
            name = drug_id

        if not name:
            continue

        key = normalize_key(name)
        if key in seen:
            continue

        seen.add(key)

        drugs.append(
            {
                "id": drug_id,
                "name": name,
                "label": name,
                "value": name,
                "smiles": smiles,
            }
        )

    drugs.sort(key=lambda x: x["name"].lower())
    return drugs


def load_drug_smiles_map(dataset=DEFAULT_DATASET):
    dataset = clean_dataset(dataset)

    mp = {}

    for d in load_drugs(dataset):
        name = normalize_key(d.get("name"))
        did = normalize_key(d.get("id"))
        smiles = normalize_text(d.get("smiles"))

        if name:
            mp[name] = smiles

        if did:
            mp[did] = smiles

    return mp


def find_smiles_for_drug(drug_name, dataset=DEFAULT_DATASET):
    dataset = clean_dataset(dataset)

    mp = load_drug_smiles_map(dataset)
    return mp.get(normalize_key(drug_name), "")


def list_disease_candidates(dataset=DEFAULT_DATASET, limit=1000):
    dataset = clean_dataset(dataset)

    # Thử lấy từ predictor đã load (có disease_names chính xác)
    predictor = _current_predictors.get(dataset)
    if predictor and hasattr(predictor, 'disease_names') and predictor.disease_names:
        diseases = []
        for name in predictor.disease_names[:limit]:
            diseases.append({"id": "", "name": name, "label": name, "value": name})
        diseases.sort(key=lambda x: x["name"].lower())
        return diseases

    # Fallback: đọc từ Allnode.csv, lấy disease range dựa vào DrugInformation.csv row count
    file_path = get_allnode_file(dataset)
    if not file_path or not Path(file_path).exists():
        return []

    # Đọc tất cả nodes (file không có header)
    import csv
    all_nodes = []
    encodings = ["utf-8-sig", "utf-8", "latin1"]
    for enc in encodings:
        try:
            with open(file_path, "r", encoding=enc, newline="") as f:
                reader = csv.reader(f)
                for row in reader:
                    if len(row) >= 2:
                        all_nodes.append(row[1].strip())
                    elif len(row) == 1:
                        all_nodes.append(row[0].strip())
            break
        except Exception:
            all_nodes = []

    if not all_nodes:
        return []

    # Xác định drug_count từ DrugInformation.csv
    drug_info_path = get_drug_info_file(dataset)
    drug_count = 0
    if drug_info_path and Path(drug_info_path).exists():
        drug_count = len(read_csv_rows(drug_info_path))

    # Xác định disease_count từ DiseaseFeature.csv hoặc DrugDiseaseAssociationNumber.csv
    dpath = dataset_path(dataset)
    disease_feature_path = find_file_case_insensitive(dpath, ["DiseaseFeature.csv"])
    disease_count = 0
    if disease_feature_path and Path(disease_feature_path).exists():
        # DiseaseFeature.csv không có header, mỗi dòng = 1 disease
        with open(disease_feature_path, "r", encoding="utf-8-sig") as f:
            disease_count = sum(1 for _ in f)

    if drug_count == 0 or disease_count == 0:
        return []

    # Disease nodes: index drug_count -> drug_count + disease_count
    disease_nodes = all_nodes[drug_count:drug_count + disease_count]

    diseases = []
    seen = set()
    for name in disease_nodes:
        if not name:
            continue
        key = normalize_key(name)
        if key in seen:
            continue
        seen.add(key)
        diseases.append({"id": "", "name": name, "label": name, "value": name})
        if len(diseases) >= limit:
            break

    diseases.sort(key=lambda x: x["name"].lower())
    return diseases


# =========================================================
# PREDICTOR CACHE
# =========================================================

_current_predictors = {}
_original_predictors = {}

_predictor_errors = {
    "current": None,
    "original": None,
}


def make_predictor_instance(PredictorClass, dataset):
    """
    Không truyền cpu/cuda vào đây.
    Predictor của bạn nhận dataset như B-dataset/C-dataset/F-dataset.
    """
    dataset = clean_dataset(dataset)

    attempts = [
        lambda: PredictorClass(dataset=dataset),
        lambda: PredictorClass(dataset),
        lambda: PredictorClass(),
    ]

    last_error = None

    for fn in attempts:
        try:
            obj = fn()

            try:
                obj.device = DEVICE
            except Exception:
                pass

            try:
                obj.dataset = dataset
            except Exception:
                pass

            return obj

        except TypeError as e:
            last_error = e
            continue

    raise RuntimeError(f"Không khởi tạo được Predictor. Lỗi cuối: {last_error}")


def try_import_current_predictor(dataset=DEFAULT_DATASET):
    dataset = clean_dataset(dataset)

    if dataset in _current_predictors:
        return _current_predictors[dataset]

    try:
        if str(BASE_DIR) not in sys.path:
            sys.path.insert(0, str(BASE_DIR))

        import predictor as current_module

        PredictorClass = None

        for cls_name in [
            "AMDGTPredictor",
            "Predictor",
            "MedLinkPredictor",
            "DdaPredictor",
            "DDAPredictor",
        ]:
            if hasattr(current_module, cls_name):
                PredictorClass = getattr(current_module, cls_name)
                break

        if PredictorClass is None:
            raise RuntimeError("Không tìm thấy class Predictor trong ai_api/predictor.py")

        obj = make_predictor_instance(PredictorClass, dataset)

        _current_predictors[dataset] = obj
        _predictor_errors["current"] = None

        return obj

    except Exception as e:
        _predictor_errors["current"] = str(e)
        print("[CURRENT PREDICTOR ERROR]", e)
        traceback.print_exc()
        return None


def try_import_original_predictor(dataset=DEFAULT_DATASET):
    dataset = clean_dataset(dataset)

    if dataset in _original_predictors:
        return _original_predictors[dataset]

    try:
        import importlib.util

        original_predictor_path = AMDGT_ORIGINAL_DIR / "predictor.py"

        if not original_predictor_path.exists():
            raise FileNotFoundError(f"Không thấy file: {original_predictor_path}")

        spec = importlib.util.spec_from_file_location(
            "amdgt_original_predictor",
            str(original_predictor_path),
        )

        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)

        PredictorClass = None

        for cls_name in [
            "AMDGTPredictor",
            "Predictor",
            "OriginalPredictor",
            "DdaPredictor",
            "DDAPredictor",
        ]:
            if hasattr(module, cls_name):
                PredictorClass = getattr(module, cls_name)
                break

        if PredictorClass is None:
            raise RuntimeError("Không tìm thấy class Predictor trong AMDGT_ORIGINAL/predictor.py")

        obj = make_predictor_instance(PredictorClass, dataset)

        _original_predictors[dataset] = obj
        _predictor_errors["original"] = None

        return obj

    except Exception as e:
        _predictor_errors["original"] = str(e)
        print("[ORIGINAL PREDICTOR ERROR]", e)
        traceback.print_exc()
        return None


def call_predictor(predictor_obj, input_type, keyword, dataset, top_k):
    """
    Gọi hàm predict theo nhiều dạng khác nhau.
    Đặt input_type trước keyword để tránh lỗi:
    input_type phải là drug hoặc disease.
    """
    if predictor_obj is None:
        raise RuntimeError("Predictor chưa load được")

    dataset = clean_dataset(dataset)

    method_names = [
        "predict",
        "predict_topk",
        "predict_ddi",
        "predict_dda",
        "run_predict",
    ]

    errors = []

    for method_name in method_names:
        if not hasattr(predictor_obj, method_name):
            continue

        method = getattr(predictor_obj, method_name)

        call_styles = [
            lambda: method(input_type, keyword, top_k),
            lambda: method(input_type, keyword, dataset, top_k),
            lambda: method(
                input_type=input_type,
                keyword=keyword,
                top_k=top_k,
            ),
            lambda: method(
                input_type=input_type,
                keyword=keyword,
                dataset=dataset,
                top_k=top_k,
            ),
            lambda: method(
                input_type=input_type,
                query=keyword,
                dataset=dataset,
                top_k=top_k,
            ),
            lambda: method(keyword, top_k),
            lambda: method(keyword),
        ]

        for call in call_styles:
            try:
                return call()
            except TypeError as e:
                errors.append(str(e))
                continue
            except ValueError as e:
                if "input_type" in str(e):
                    errors.append(str(e))
                    continue
                raise

    raise RuntimeError("Không gọi được hàm predict. Lỗi: " + " | ".join(errors[-5:]))


# =========================================================
# FALLBACK RESULT
# =========================================================

def fallback_predict(input_type, keyword, dataset, top_k, model_name="fallback"):
    dataset = clean_dataset(dataset)
    top_k = safe_int(top_k, DEFAULT_TOP_K, 1, 100)

    if input_type == "drug":
        diseases = list_disease_candidates(dataset, limit=max(top_k, 20))

        if not diseases:
            diseases = [
                {"id": "D001", "name": "Hypertension"},
                {"id": "D002", "name": "Diabetes Mellitus"},
                {"id": "D003", "name": "Arthritis"},
                {"id": "D004", "name": "Asthma"},
                {"id": "D005", "name": "Inflammation"},
            ]

        results = []

        for i, d in enumerate(diseases[:top_k]):
            results.append(
                {
                    "rank": i + 1,
                    "drug": keyword,
                    "disease": d.get("name", ""),
                    "drug_name": keyword,
                    "disease_name": d.get("name", ""),
                    "score": round(0.95 - i * 0.017, 6),
                    "dataset": dataset,
                    "model": model_name,
                    "smiles": find_smiles_for_drug(keyword, dataset),
                    "is_fallback": True,
                }
            )

        return results

    drugs = load_drugs(dataset)

    if not drugs:
        drugs = [
            {
                "id": "DB0001",
                "name": "Aspirin",
                "smiles": "CC(=O)OC1=CC=CC=C1C(=O)O",
            },
            {
                "id": "DB0002",
                "name": "Acetaminophen",
                "smiles": "CC(=O)NC1=CC=C(O)C=C1",
            },
            {
                "id": "DB0003",
                "name": "Ibuprofen",
                "smiles": "CC(C)CC1=CC=C(C=C1)C(C)C(=O)O",
            },
        ]

    results = []

    for i, d in enumerate(drugs[:top_k]):
        results.append(
            {
                "rank": i + 1,
                "drug": d.get("name", ""),
                "disease": keyword,
                "drug_name": d.get("name", ""),
                "disease_name": keyword,
                "score": round(0.95 - i * 0.017, 6),
                "dataset": dataset,
                "model": model_name,
                "smiles": d.get("smiles", ""),
                "is_fallback": True,
            }
        )

    return results


def to_float_safe(x):
    try:
        return float(x)
    except Exception:
        return x


def normalize_prediction_result(raw, input_type, keyword, dataset, model_name):
    dataset = clean_dataset(dataset)

    if raw is None:
        return []

    if isinstance(raw, dict):
        if isinstance(raw.get("results"), list):
            raw = raw["results"]
        elif isinstance(raw.get("data"), list):
            raw = raw["data"]
        elif isinstance(raw.get("predictions"), list):
            raw = raw["predictions"]
        else:
            raw = [raw]

    if not isinstance(raw, list):
        raw = [raw]

    results = []

    for i, item in enumerate(raw):
        if isinstance(item, dict):
            drug = (
                item.get("drug")
                or item.get("drug_name")
                or item.get("drugName")
                or item.get("Drug")
                or ""
            )

            disease = (
                item.get("disease")
                or item.get("disease_name")
                or item.get("diseaseName")
                or item.get("Disease")
                or ""
            )

            # Nếu predictor trả "name" (tên kết quả predict), gán vào đúng field
            if not drug and not disease and item.get("name"):
                if input_type == "drug":
                    disease = item["name"]
                else:
                    drug = item["name"]

            if input_type == "drug" and not drug:
                drug = keyword

            if input_type == "disease" and not disease:
                disease = keyword

            score = (
                item.get("score")
                or item.get("probability")
                or item.get("pred_score")
                or item.get("value")
                or 0
            )

            smiles = (
                item.get("smiles")
                or item.get("SMILES")
                or item.get("drug_smiles")
                or find_smiles_for_drug(drug, dataset)
            )

            results.append(
                {
                    "rank": item.get("rank", i + 1),
                    "drug": normalize_text(drug),
                    "disease": normalize_text(disease),
                    "drug_name": normalize_text(drug),
                    "disease_name": normalize_text(disease),
                    "score": to_float_safe(score),
                    "dataset": item.get("dataset", dataset),
                    "model": model_name,
                    "smiles": normalize_text(smiles),
                    "is_fallback": bool(item.get("is_fallback", False)),
                }
            )

        elif isinstance(item, (list, tuple)):
            drug = ""
            disease = ""
            score = 0

            if len(item) >= 3:
                if input_type == "drug":
                    drug = keyword
                    disease = item[0]
                    score = item[2]
                else:
                    drug = item[0]
                    disease = keyword
                    score = item[2]

            elif len(item) == 2:
                if input_type == "drug":
                    drug = keyword
                    disease = item[0]
                    score = item[1]
                else:
                    drug = item[0]
                    disease = keyword
                    score = item[1]

            results.append(
                {
                    "rank": i + 1,
                    "drug": normalize_text(drug),
                    "disease": normalize_text(disease),
                    "drug_name": normalize_text(drug),
                    "disease_name": normalize_text(disease),
                    "score": to_float_safe(score),
                    "dataset": dataset,
                    "model": model_name,
                    "smiles": find_smiles_for_drug(drug, dataset),
                    "is_fallback": False,
                }
            )

        else:
            if input_type == "drug":
                drug = keyword
                disease = str(item)
            else:
                drug = str(item)
                disease = keyword

            results.append(
                {
                    "rank": i + 1,
                    "drug": drug,
                    "disease": disease,
                    "drug_name": drug,
                    "disease_name": disease,
                    "score": 0,
                    "dataset": dataset,
                    "model": model_name,
                    "smiles": find_smiles_for_drug(drug, dataset),
                    "is_fallback": False,
                }
            )

    return results


# =========================================================
# GENERATE DRUG
# =========================================================

def is_valid_smiles_basic(smiles):
    smiles = normalize_text(smiles)

    if len(smiles) < 3:
        return False

    low = smiles.lower()

    for bad in [" ", "\n", "\t", "nan", "none", "null"]:
        if bad in low:
            return False

    allowed_chars = set(
        "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
        "abcdefghijklmnopqrstuvwxyz"
        "0123456789"
        "@+-=#$/\\().[]%"
    )

    return all(c in allowed_chars for c in smiles)


def fallback_generate_smiles(n=10):
    examples = [
        "CC(=O)NC1=CC=C(O)C=C1",
        "CC(=O)OC1=CC=CC=C1C(=O)O",
        "CC(C)CC1=CC=C(C=C1)C(C)C(=O)O",
        "CN1C=NC2=C1C(=O)N(C(=O)N2C)C",
        "CCN(CC)CCOC(=O)C1=CC=CC=C1",
        "COC1=CC=C(C=C1)CCN",
        "CC(C)NCC(O)COC1=CC=CC=C1",
        "CCOC(=O)C1=CC=CC=C1O",
        "CCC1=CC=CC=C1O",
        "CCN1CCCC1CNC(=O)C1=CC=CC=C1",
        "COC1=CC2=C(C=C1)N=CN=C2N",
        "CC(C)(C)NCC(O)COC1=CC=CC=C1",
    ]

    random.shuffle(examples)
    return examples[:n]


def call_generator(target_disease, n, seed_smiles="", symptoms=None):
    n = safe_int(n, 10, 1, 50)

    try:
        if str(BASE_DIR) not in sys.path:
            sys.path.insert(0, str(BASE_DIR))

        import generator

        for fn_name in [
            "generate_drug",
            "generate_smiles",
            "generate",
        ]:
            if hasattr(generator, fn_name):
                fn = getattr(generator, fn_name)

                call_styles = [
                    lambda: fn(target_disease=target_disease, n=n, seed_smiles=seed_smiles, symptoms=symptoms or []),
                    lambda: fn(target_disease=target_disease, n=n, seed_smiles=seed_smiles),
                    lambda: fn(target_disease=target_disease, n=n),
                    lambda: fn(disease=target_disease, n=n),
                    lambda: fn(target_disease, n),
                    lambda: fn(n),
                ]

                for call in call_styles:
                    try:
                        raw = call()

                        if isinstance(raw, dict):
                            raw = (
                                raw.get("smiles")
                                or raw.get("results")
                                or raw.get("data")
                                or []
                            )

                        if isinstance(raw, str):
                            raw = [raw]

                        if isinstance(raw, list):
                            return raw

                    except TypeError:
                        continue

    except Exception as e:
        print("[GENERATOR WARNING]", e)

    return fallback_generate_smiles(n)


def build_graph_data(input_type, keyword, dataset, current_results, original_results):
    """Build vis.js graph: center node = input, edges to predicted results"""
    nodes = []
    edges = []
    node_ids = set()

    # Center node (input)
    center_id = f"input_{keyword}"
    center_type = "drug" if input_type == "drug" else "disease"
    nodes.append({
        "id": center_id,
        "label": keyword,
        "type": center_type,
        "score": None,
        "smiles": "",
        "model_type": "input"
    })
    node_ids.add(center_id)

    # Add result nodes from current model
    for r in (current_results or [])[:8]:
        # Lấy tên kết quả: nếu input=drug → kết quả=disease, ngược lại
        if input_type == "drug":
            name = r.get("disease_name") or r.get("name") or ""
        else:
            name = r.get("drug_name") or r.get("name") or ""
        if not name or name.lower() == keyword.lower():
            continue
        node_id = f"current_{name}"
        result_type = "disease" if input_type == "drug" else "drug"
        if node_id not in node_ids:
            nodes.append({
                "id": node_id,
                "label": name,
                "type": result_type,
                "score": r.get("score"),
                "smiles": r.get("smiles", "") if result_type == "drug" else "",
                "model_type": "current"
            })
            node_ids.add(node_id)
        edges.append({"from": center_id, "to": node_id, "model": "current"})

    # Add result nodes from original model
    for r in (original_results or [])[:8]:
        if input_type == "drug":
            name = r.get("disease_name") or r.get("name") or ""
        else:
            name = r.get("drug_name") or r.get("name") or ""
        if not name or name.lower() == keyword.lower():
            continue
        node_id = f"original_{name}"
        result_type = "disease" if input_type == "drug" else "drug"

        # Check if same name already exists from current (shared node)
        shared_id = f"current_{name}"
        if shared_id in node_ids:
            edges.append({"from": center_id, "to": shared_id, "model": "original"})
        else:
            if node_id not in node_ids:
                nodes.append({
                    "id": node_id,
                    "label": name,
                    "type": result_type,
                    "score": r.get("score"),
                    "smiles": r.get("smiles", "") if result_type == "drug" else "",
                    "model_type": "original"
                })
                node_ids.add(node_id)
            edges.append({"from": center_id, "to": node_id, "model": "original"})

    return {"nodes": nodes, "edges": edges}


# =========================================================
# CORE PREDICT LOGIC
# =========================================================

def run_predict_logic(data):
    dataset = clean_dataset(data.get("dataset", DEFAULT_DATASET))

    input_type = normalize_key(data.get("input_type", "drug"))

    if input_type in ["thuoc", "drug_to_disease", "drug"]:
        input_type = "drug"
    elif input_type in ["benh", "disease_to_drug", "disease"]:
        input_type = "disease"
    else:
        input_type = "drug"

    keyword = (
        data.get("keyword")
        or data.get("query")
        or data.get("drug")
        or data.get("disease")
        or data.get("input")
        or data.get("value")
        or ""
    )

    keyword = normalize_text(keyword)

    top_k = safe_int(
        data.get("top_k", DEFAULT_TOP_K),
        DEFAULT_TOP_K,
        1,
        100,
    )

    if not keyword:
        return {
            "ok": False,
            "error": "Thiếu keyword/input",
            "received": data,
        }, 400

    response = {
        "ok": True,
        "dataset": dataset,
        "input_type": input_type,
        "keyword": keyword,
        "top_k": top_k,
        "device": str(DEVICE),
        "cuda_available": torch.cuda.is_available(),
        "torch": torch.__version__,
        "current": {
            "ok": False,
            "results": [],
            "error": None,
        },
        "original": {
            "ok": False,
            "results": [],
            "error": None,
        },
    }

    # =========================
    # MODEL HIỆN TẠI
    # =========================

    try:
        current = try_import_current_predictor(dataset)

        if current is None:
            raise RuntimeError(
                _predictor_errors.get("current")
                or "Không load được predictor hiện tại"
            )

        raw_current = call_predictor(
            current,
            input_type,
            keyword,
            dataset,
            top_k,
        )

        current_results = normalize_prediction_result(
            raw_current,
            input_type=input_type,
            keyword=keyword,
            dataset=dataset,
            model_name="current",
        )

        response["current"]["ok"] = True
        response["current"]["results"] = current_results[:top_k]

    except Exception as e:
        print("[CURRENT PREDICT ERROR]", e)
        traceback.print_exc()

        response["current"]["ok"] = False
        response["current"]["error"] = str(e)
        response["current"]["results"] = fallback_predict(
            input_type,
            keyword,
            dataset,
            top_k,
            model_name="current_fallback",
        )

    # =========================
    # MODEL GỐC
    # =========================

    try:
        original = try_import_original_predictor(dataset)

        if original is None:
            raise RuntimeError(
                _predictor_errors.get("original")
                or "Không load được predictor gốc"
            )

        raw_original = call_predictor(
            original,
            input_type,
            keyword,
            dataset,
            top_k,
        )

        original_results = normalize_prediction_result(
            raw_original,
            input_type=input_type,
            keyword=keyword,
            dataset=dataset,
            model_name="original",
        )

        response["original"]["ok"] = True
        response["original"]["results"] = original_results[:top_k]

    except Exception as e:
        print("[ORIGINAL PREDICT ERROR]", e)
        traceback.print_exc()

        response["original"]["ok"] = False
        response["original"]["error"] = str(e)
        response["original"]["results"] = fallback_predict(
            input_type,
            keyword,
            dataset,
            top_k,
            model_name="original_fallback",
        )

    # =========================
    # BUILD GRAPH DATA
    # =========================
    response["graph"] = build_graph_data(
        input_type, keyword, dataset,
        response.get("current", {}).get("results", []),
        response.get("original", {}).get("results", []),
    )

    return response, 200


# =========================================================
# ROUTES
# =========================================================

@app.route("/", methods=["GET"])
def home():
    return jsonify(
        {
            "ok": True,
            "name": "MedLink AI Flask API",
            "routes": [
                "/health",
                "/drugs",
                "/diseases",
                "/drug_options",
                "/disease_options",
                "/predict",
                "/predict_compare",
                "/generate_drug",
                "/reload",
            ],
        }
    )


@app.route("/health", methods=["GET"])
def health():
    return jsonify(
        {
            "ok": True,
            "message": "MedLink AI Flask API is running",
            "python": sys.executable,
            "base_dir": str(BASE_DIR),
            "torch": torch.__version__,
            "cuda_available": torch.cuda.is_available(),
            "torch_cuda": torch.version.cuda,
            "device": torch.cuda.get_device_name(0)
            if torch.cuda.is_available()
            else "CPU",
            "datasets": DATASETS,
            "current_predictor_error": _predictor_errors.get("current"),
            "original_predictor_error": _predictor_errors.get("original"),
        }
    )


@app.route("/drugs", methods=["GET"])
def drugs():
    dataset = clean_dataset(request.args.get("dataset", DEFAULT_DATASET))
    q = normalize_key(request.args.get("q", ""))
    limit = safe_int(request.args.get("limit", 500), 500, 1, 5000)

    items = load_drugs(dataset)

    if q:
        items = [
            x
            for x in items
            if q in normalize_key(x.get("name"))
            or q in normalize_key(x.get("id"))
        ]

    return jsonify(
        {
            "ok": True,
            "dataset": dataset,
            "count": len(items[:limit]),
            "drugs": items[:limit],
            "options": items[:limit],
        }
    )


@app.route("/drug_options", methods=["GET"])
def drug_options():
    dataset = clean_dataset(request.args.get("dataset", DEFAULT_DATASET))
    q = normalize_key(request.args.get("q", ""))
    limit = safe_int(request.args.get("limit", 700), 700, 1, 5000)

    items = load_drugs(dataset)

    if q:
        items = [
            x
            for x in items
            if q in normalize_key(x.get("name"))
            or q in normalize_key(x.get("id"))
        ]

    options = []

    for x in items[:limit]:
        options.append(
            {
                "id": x.get("id", ""),
                "name": x.get("name", ""),
                "label": x.get("name", ""),
                "value": x.get("name", ""),
                "smiles": x.get("smiles", ""),
            }
        )

    return jsonify(
        {
            "ok": True,
            "dataset": dataset,
            "count": len(options),
            "options": options,
            "drugs": options,
        }
    )


@app.route("/diseases", methods=["GET"])
def diseases():
    dataset = clean_dataset(request.args.get("dataset", DEFAULT_DATASET))
    q = normalize_key(request.args.get("q", ""))
    limit = safe_int(request.args.get("limit", 500), 500, 1, 5000)

    items = list_disease_candidates(dataset, limit=5000)

    if q:
        items = [
            x
            for x in items
            if q in normalize_key(x.get("name"))
            or q in normalize_key(x.get("id"))
        ]

    return jsonify(
        {
            "ok": True,
            "dataset": dataset,
            "count": len(items[:limit]),
            "diseases": items[:limit],
            "options": items[:limit],
        }
    )


@app.route("/disease_options", methods=["GET"])
def disease_options():
    dataset = clean_dataset(request.args.get("dataset", DEFAULT_DATASET))
    q = normalize_key(request.args.get("q", ""))
    limit = safe_int(request.args.get("limit", 700), 700, 1, 5000)

    print(f"[disease_options] dataset={dataset}, limit={limit}")
    items = list_disease_candidates(dataset, limit=5000)
    print(f"[disease_options] found {len(items)} diseases")

    if q:
        items = [
            x
            for x in items
            if q in normalize_key(x.get("name"))
            or q in normalize_key(x.get("id"))
        ]

    options = []

    for x in items[:limit]:
        options.append(
            {
                "id": x.get("id", ""),
                "name": x.get("name", ""),
                "label": x.get("name", ""),
                "value": x.get("name", ""),
            }
        )

    return jsonify(
        {
            "ok": True,
            "dataset": dataset,
            "count": len(options),
            "options": options,
            "diseases": options,
        }
    )


@app.route("/predict", methods=["POST", "OPTIONS"])
def predict():
    if request.method == "OPTIONS":
        return jsonify({"ok": True})

    data = request.get_json(silent=True) or request.form.to_dict() or {}

    result, status = run_predict_logic(data)
    return jsonify(result), status


@app.route("/predict_compare", methods=["POST", "OPTIONS"])
def predict_compare():
    if request.method == "OPTIONS":
        return jsonify({"ok": True})

    data = request.get_json(silent=True) or request.form.to_dict() or {}

    result, status = run_predict_logic(data)

    if status != 200:
        return jsonify(result), status

    result["current_results"] = result.get("current", {}).get("results", [])
    result["original_results"] = result.get("original", {}).get("results", [])

    return jsonify(result), 200


@app.route("/generate_drug", methods=["POST", "OPTIONS"])
def generate_drug():
    if request.method == "OPTIONS":
        return jsonify({"ok": True})

    data = request.get_json(silent=True) or request.form.to_dict() or {}

    dataset = clean_dataset(data.get("dataset", DEFAULT_DATASET))

    target_disease = normalize_text(
        data.get("target_disease")
        or data.get("disease")
        or data.get("keyword")
        or data.get("input")
        or ""
    )

    symptoms = data.get("symptoms") or []
    if isinstance(symptoms, str):
        symptoms = [s.strip() for s in symptoms.split(",") if s.strip()]

    seed_smiles = normalize_text(
        data.get("seed_smiles")
        or data.get("seed")
        or ""
    )

    n = safe_int(
        data.get("n")
        or data.get("num")
        or data.get("count")
        or data.get("top_k")
        or 10,
        10,
        1,
        50,
    )

    raw_smiles = call_generator(target_disease, n * 3, seed_smiles=seed_smiles, symptoms=symptoms)

    valid = []
    seen = set()

    for item in raw_smiles:
        # item có thể là string hoặc dict
        if isinstance(item, dict):
            smi = normalize_text(item.get("smiles", ""))
            base_drug = item.get("base_drug", "")
        else:
            smi = normalize_text(item)
            base_drug = ""

        if not is_valid_smiles_basic(smi):
            continue

        if smi in seen:
            continue

        seen.add(smi)

        valid.append(
            {
                "rank": len(valid) + 1,
                "smiles": smi,
                "base_drug": base_drug,
                "target_disease": target_disease,
                "dataset": dataset,
                "valid_basic": True,
            }
        )

        if len(valid) >= n:
            break

    return jsonify(
        {
            "ok": True,
            "dataset": dataset,
            "target_disease": target_disease,
            "count": len(valid),
            "results": valid,
            "smiles": [x["smiles"] for x in valid],
        }
    )


@app.route("/reload", methods=["GET", "POST"])
def reload_predictors():
    global _current_predictors, _original_predictors

    _current_predictors = {}
    _original_predictors = {}

    _predictor_errors["current"] = None
    _predictor_errors["original"] = None

    return jsonify(
        {
            "ok": True,
            "message": "Đã reset predictor cache.",
        }
    )


@app.route("/render_smiles", methods=["GET"])
def render_smiles():
    """Render SMILES thành ảnh PNG đẹp bằng RDKit"""
    import io
    from flask import send_file

    smiles = request.args.get("smi", "")
    width = safe_int(request.args.get("w", 300), 300, 100, 800)
    height = safe_int(request.args.get("h", 300), 300, 100, 800)

    if not smiles:
        return "Missing smi parameter", 400

    try:
        from rdkit import Chem
        from rdkit.Chem import Draw

        mol = Chem.MolFromSmiles(smiles)
        if mol is None:
            return "Invalid SMILES", 400

        img = Draw.MolToImage(mol, size=(width, height))

        buf = io.BytesIO()
        img.save(buf, format="PNG")
        buf.seek(0)
        return send_file(buf, mimetype="image/png", max_age=86400)

    except Exception as e:
        return f"Error: {e}", 500


# =========================================================
# RUN
# =========================================================

def preload_all_datasets():
    """Load predictors cho tất cả datasets khi khởi động"""
    print("\n" + "=" * 50)
    print("PRELOADING ALL DATASETS...")
    print("=" * 50)
    for ds in DATASETS:
        print(f"\n--- Loading {ds} ---")
        try:
            try_import_current_predictor(ds)
            print(f"[{ds}] Current: OK")
        except Exception as e:
            print(f"[{ds}] Current: FAILED - {e}")
        try:
            try_import_original_predictor(ds)
            print(f"[{ds}] Original: OK")
        except Exception as e:
            print(f"[{ds}] Original: FAILED - {e}")
    print("=" * 50 + "\n")


if __name__ == "__main__":
    preload_all_datasets()
    app.run(
        host="127.0.0.1",
        port=5000,
        debug=True,
        use_reloader=False,
    )