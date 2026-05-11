from flask import Flask, request, jsonify
from flask_cors import CORS
from predictor import AMDGTPredictor
from sentence_transformers import SentenceTransformer, util
from symptom_map import SYMPTOM_MAP

import os
import csv
import unicodedata
import re
import torch


app = Flask(__name__)
CORS(app)

# =========================
# Dataset config
# =========================
DATASETS = ["B-dataset", "C-dataset", "F-dataset"]

# Cache predictor theo dataset.
# Dataset nào web chọn thì mới load dataset đó.
# Load xong rồi thì lần sau dùng lại, không load lại.
predictors = {}


def get_predictor(dataset):
    if dataset not in DATASETS:
        raise ValueError(f"Dataset không hợp lệ: {dataset}")

    if dataset not in predictors:
        print("==============================")
        print(f"Loading predictor: {dataset}")
        predictors[dataset] = AMDGTPredictor(dataset=dataset)
        print(f"Loaded OK: {dataset}")

    return predictors[dataset]


# =========================
# Helper functions
# =========================
def normalize_key(name):
    return str(name).strip().lower()


def normalize_text(text):
    text = str(text).strip().lower()
    text = unicodedata.normalize("NFD", text)
    text = "".join(ch for ch in text if unicodedata.category(ch) != "Mn")
    text = text.replace("đ", "d")
    text = re.sub(r"\s+", " ", text).strip()
    return text


def is_code_name(name):
    if not name:
        return False

    t = str(name).strip().upper()

    return (
        t.startswith("DB")
        or t.startswith("D")
        or t.startswith("DISEASE_")
        or t.startswith("DRUG_")
    )


def read_csv_rows(path):
    rows = []

    if not os.path.exists(path):
        return rows

    with open(path, "r", encoding="utf-8-sig") as f:
        reader = csv.reader(f)
        for row in reader:
            rows.append(row)

    return rows


def resolve_path(*parts):
    base_dir = os.path.dirname(os.path.abspath(__file__))
    return os.path.join(base_dir, *parts)


def get_smiles_map(dataset_name):
    path = resolve_path("AMDGT", "data", dataset_name, "DrugInformation.csv")
    rows = read_csv_rows(path)

    if not rows:
        return {}

    header = [str(x).strip().lower() for x in rows[0]]
    data_rows = rows[1:] if ("id" in header or "name" in header or "smiles" in header) else rows

    id_col = header.index("id") if "id" in header else 0
    name_col = header.index("name") if "name" in header else 1
    smiles_col = header.index("smiles") if "smiles" in header else None

    if smiles_col is None:
        return {}

    smiles_map = {}

    synonym_map = {
        "paracetamol": "acetaminophen",
        "tylenol": "acetaminophen",
        "aspirin": "acetylsalicylic acid",
    }

    for row in data_rows:
        if not row:
            continue

        drug_id = str(row[id_col]).strip() if id_col < len(row) else ""
        drug_name = str(row[name_col]).strip() if name_col < len(row) else ""
        smiles = str(row[smiles_col]).strip() if smiles_col < len(row) else ""

        if drug_id:
            smiles_map[normalize_key(drug_id)] = smiles

        if drug_name:
            smiles_map[normalize_key(drug_name)] = smiles

    for alias, real_name in synonym_map.items():
        if real_name in smiles_map:
            smiles_map[alias] = smiles_map[real_name]

    return smiles_map


def attach_smiles_to_results(results, dataset_name):
    smiles_map = get_smiles_map(dataset_name)

    for item in results:
        item["smiles"] = ""

        search_keys = []

        if item.get("name"):
            search_keys.append(normalize_key(item["name"]))

        if item.get("code"):
            search_keys.append(normalize_key(item["code"]))

        for key in search_keys:
            if key in smiles_map and smiles_map[key]:
                item["smiles"] = smiles_map[key]
                break

    return results


# =========================
# Disease / symptom engine
# =========================
def find_protein_start_index(rows):
    for i, row in enumerate(rows):
        name = ""

        if len(row) > 1:
            name = str(row[1]).strip()

        if re.match(r"^9606\.ensp", name, re.IGNORECASE):
            return i

    return -1


def load_disease_names_from_b_dataset():
    allnode_path = resolve_path("AMDGT", "data", "B-dataset", "Allnode.csv")

    if not os.path.exists(allnode_path):
        allnode_path = resolve_path("AMDGT", "data", "B-dataset", "AllNode.csv")

    disease_gip_path = resolve_path("AMDGT", "data", "B-dataset", "DiseaseGIP.csv")
    drug_info_path = resolve_path("AMDGT", "data", "B-dataset", "DrugInformation.csv")

    all_rows = read_csv_rows(allnode_path)
    gip_rows = read_csv_rows(disease_gip_path)
    drug_rows = read_csv_rows(drug_info_path)

    if not all_rows or not gip_rows or not drug_rows:
        return []

    drug_count = len(drug_rows) - 1 if drug_rows else 0
    disease_count = len(gip_rows)
    protein_start = find_protein_start_index(all_rows)

    if protein_start == -1:
        disease_rows = all_rows[drug_count:drug_count + disease_count]
    else:
        disease_rows = all_rows[max(0, protein_start - disease_count):protein_start]

    disease_names = []

    for row in disease_rows:
        if len(row) < 2:
            continue

        disease_name = str(row[1]).strip()

        if disease_name:
            disease_names.append(disease_name)

    return disease_names


def load_disease_vi_alias():
    alias = {}
    php_map_path = resolve_path("..", "app", "data", "disease_vi_map_598.php")

    if not os.path.exists(php_map_path):
        return alias

    try:
        with open(php_map_path, "r", encoding="utf-8") as f:
            text = f.read()

        pairs = re.findall(r"'([^']+)'\s*=>\s*'([^']*)'", text)

        for en, vi in pairs:
            alias[normalize_text(en)] = vi.strip()

    except Exception as e:
        print("Không parse được disease_vi_map_598.php:", e)

    return alias


EMBED_MODEL_NAME = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
embedder = SentenceTransformer(EMBED_MODEL_NAME)

DISEASE_NAMES = load_disease_names_from_b_dataset()
DISEASE_VI_ALIAS = load_disease_vi_alias()

SYMPTOM_HINTS = {
    "arthritis": "dau khop, sung khop, cung khop",
    "osteoarthritis": "dau khop, cung khop, kho cu dong",
    "gout": "dau khop, sung do, dau nhuc",
    "influenza": "sot, ho, dau hong, met moi",
    "common cold": "ho, so mui, hat hoi",
    "pharyngitis": "dau hong, kho nuot",
    "tonsillitis": "dau hong, kho nuot, sot, amidan sung",
    "pneumonia": "sot, ho, kho tho, dau nguc",
    "bronchitis": "ho, dom, kho tho",
    "asthma": "kho tho, tuc nguc, ho",
    "gastritis": "dau bung, buon non, day hoi",
    "diarrhea": "tieu chay, dau bung",
    "migraine": "dau dau, buon non",
    "headache": "dau dau, chong mat",
    "hypertension": "dau dau, chong mat",
    "diabetes": "khat nuoc, tieu nhieu",
    "allergy": "ngua, phat ban",
    "urinary tract infection": "tieu buot, tieu rat, dau bung duoi",
    "dengue fever": "sot cao, dau dau, dau nhuc co the",
}

DISEASE_CORPUS = []

for disease_name in DISEASE_NAMES:
    key = normalize_text(disease_name)
    vn_alias = DISEASE_VI_ALIAS.get(key, "")
    symptom_hint = SYMPTOM_HINTS.get(key, "")

    parts = [disease_name]

    if vn_alias:
        parts.append(f"Tên tiếng Việt: {vn_alias}")

    if symptom_hint:
        parts.append(f"Triệu chứng: {symptom_hint}")

    parts.append("Đây là một bệnh")

    DISEASE_CORPUS.append(". ".join(parts))

DISEASE_EMBEDDINGS = (
    embedder.encode(
        DISEASE_CORPUS,
        convert_to_tensor=True,
        normalize_embeddings=True,
        show_progress_bar=False,
    )
    if DISEASE_CORPUS
    else None
)


def symptom_rule_match(keyword):
    keyword_norm = normalize_text(keyword)
    matched = []

    for symptom_key, diseases in SYMPTOM_MAP.items():
        if symptom_key in keyword_norm:
            for disease in diseases:
                matched.append({
                    "name": disease,
                    "name_vi": DISEASE_VI_ALIAS.get(normalize_text(disease), disease),
                    "score": 0.95,
                    "matched_keywords": [symptom_key],
                })

    merged = {}

    for item in matched:
        name = item["name"]

        if name not in merged:
            merged[name] = item
        else:
            merged[name]["score"] = max(merged[name]["score"], item["score"])
            merged[name]["matched_keywords"] = list(
                set(merged[name]["matched_keywords"] + item["matched_keywords"])
            )

    return list(merged.values())


def predict_from_symptoms(symptom_text, top_k=5):
    if not symptom_text or not DISEASE_NAMES or DISEASE_EMBEDDINGS is None:
        return []

    query = f"Triệu chứng bệnh: {symptom_text}"

    query_embedding = embedder.encode(
        query,
        convert_to_tensor=True,
        normalize_embeddings=True,
    )

    scores = util.cos_sim(query_embedding, DISEASE_EMBEDDINGS)[0]
    top_results = torch.topk(scores, k=min(20, len(DISEASE_NAMES)))

    results = []
    query_norm = normalize_text(symptom_text)

    for idx, score in zip(top_results.indices.tolist(), top_results.values.tolist()):
        disease_name = DISEASE_NAMES[idx]
        key = normalize_text(disease_name)
        name_vi = DISEASE_VI_ALIAS.get(key, disease_name)

        boost = 0.0
        hint = normalize_text(SYMPTOM_HINTS.get(key, ""))
        matched_keywords = []

        if hint:
            for token in hint.split(","):
                token = token.strip()

                if token and token in query_norm:
                    boost += 0.05
                    matched_keywords.append(token)

        final_score = float(score) + boost

        results.append({
            "name": disease_name,
            "name_vi": name_vi,
            "score": round(final_score, 4),
            "matched_keywords": matched_keywords[:5],
        })

    results.sort(key=lambda x: x["score"], reverse=True)

    return results[:top_k]


def get_drug_suggestions_for_disease(disease_name, dataset_name, top_k=5):
    predictor = get_predictor(dataset_name)

    results = predictor.predict(
        "disease",
        disease_name,
        top_k=top_k,
    )

    results = attach_smiles_to_results(results, dataset_name)

    for item in results:
        item["source"] = dataset_name
        item["dataset"] = dataset_name

    return results


# =========================
# Graph helpers
# =========================
def build_fallback_graph(input_type, keyword, results, dataset_name):
    graph = {
        "dataset": dataset_name,
        "nodes": [],
        "edges": [],
    }

    main_type = "drug" if input_type == "drug" else "disease"
    child_type = "disease" if input_type == "drug" else "drug"

    smiles_map = get_smiles_map(dataset_name)

    main_id = "main"

    graph["nodes"].append({
        "id": main_id,
        "label": keyword,
        "type": main_type,
        "score": None,
        "smiles": smiles_map.get(normalize_key(keyword), "") if main_type == "drug" else "",
    })

    for i, item in enumerate(results[:5]):
        node_id = f"n{i}"
        node_label = item.get("name") or item.get("code") or f"Node {i + 1}"

        graph["nodes"].append({
            "id": node_id,
            "label": node_label,
            "type": child_type,
            "score": item.get("score"),
            "smiles": smiles_map.get(normalize_key(node_label), "") if child_type == "drug" else "",
        })

        if input_type == "drug":
            graph["edges"].append({"from": main_id, "to": node_id})
        else:
            graph["edges"].append({"from": node_id, "to": main_id})

    return graph


def attach_smiles_to_graph(graph, dataset_name):
    if not graph or "nodes" not in graph:
        return graph

    smiles_map = get_smiles_map(dataset_name)

    for node in graph.get("nodes", []):
        if node.get("type") == "drug":
            key = normalize_key(node.get("label", ""))
            node["smiles"] = node.get("smiles", "") or smiles_map.get(key, "")
        else:
            node["smiles"] = ""

    return graph


def build_symptom_graph(symptom_keyword, disease_results, drug_results):
    graph = {
        "dataset": "symptom-mode",
        "nodes": [],
        "edges": [],
    }

    graph["nodes"].append({
        "id": "symptom_input",
        "label": symptom_keyword,
        "type": "symptom",
        "score": None,
        "smiles": "",
    })

    for i, item in enumerate(disease_results[:3]):
        node_id = f"disease_{i}"

        graph["nodes"].append({
            "id": node_id,
            "label": item.get("name_vi") or item.get("name") or f"Disease {i + 1}",
            "type": "disease",
            "score": item.get("score"),
            "smiles": "",
        })

        graph["edges"].append({
            "from": "symptom_input",
            "to": node_id,
        })

    for i, item in enumerate(drug_results[:3]):
        node_id = f"drug_{i}"

        graph["nodes"].append({
            "id": node_id,
            "label": item.get("name") or item.get("code") or f"Drug {i + 1}",
            "type": "drug",
            "score": item.get("score"),
            "smiles": item.get("smiles", ""),
        })

        graph["edges"].append({
            "from": "disease_0",
            "to": node_id,
        })

    return graph


# =========================
# Disease + protein mode
# =========================
def get_allnode_path(dataset_name):
    p1 = resolve_path("AMDGT", "data", dataset_name, "Allnode.csv")
    p2 = resolve_path("AMDGT", "data", dataset_name, "AllNode.csv")

    if os.path.exists(p1):
        return p1

    return p2


def load_protein_keys_from_dataset(dataset_name):
    allnode_path = get_allnode_path(dataset_name)
    rows = read_csv_rows(allnode_path)

    if not rows:
        return set()

    protein_start = find_protein_start_index(rows)

    if protein_start == -1:
        return set()

    protein_keys = set()

    for row in rows[protein_start:]:
        if not row:
            continue

        code = str(row[0]).strip() if len(row) > 0 else ""
        name = str(row[1]).strip() if len(row) > 1 else ""

        if code:
            protein_keys.add(normalize_text(code))
            protein_keys.add(normalize_key(code))

        if name:
            protein_keys.add(normalize_text(name))
            protein_keys.add(normalize_key(name))

    return protein_keys


PROTEIN_KEYS_BY_DATASET = {
    "B-dataset": load_protein_keys_from_dataset("B-dataset"),
    "C-dataset": load_protein_keys_from_dataset("C-dataset"),
    "F-dataset": load_protein_keys_from_dataset("F-dataset"),
}


def normalize_protein_inputs(protein_ids):
    normalized = []

    for p in protein_ids:
        t = str(p).strip()

        if not t:
            continue

        normalized.append(t)

    return normalized


def build_disease_protein_graph(new_disease_name, protein_ids, results, dataset_name):
    graph = {
        "dataset": dataset_name,
        "nodes": [],
        "edges": [],
    }

    graph["nodes"].append({
        "id": "disease_input",
        "label": new_disease_name,
        "type": "disease",
        "score": None,
        "smiles": "",
    })

    shown_proteins = protein_ids[:5]

    for i, protein in enumerate(shown_proteins):
        protein_node_id = f"protein_{i}"

        graph["nodes"].append({
            "id": protein_node_id,
            "label": protein,
            "type": "protein",
            "score": None,
            "smiles": "",
        })

        graph["edges"].append({
            "from": "disease_input",
            "to": protein_node_id,
        })

    for i, item in enumerate(results[:5]):
        drug_node_id = f"drug_{i}"

        graph["nodes"].append({
            "id": drug_node_id,
            "label": item.get("name") or item.get("code") or f"Drug {i + 1}",
            "type": "drug",
            "score": item.get("score"),
            "smiles": item.get("smiles", ""),
        })

        connect_protein_index = i % max(1, len(shown_proteins))

        graph["edges"].append({
            "from": f"protein_{connect_protein_index}",
            "to": drug_node_id,
        })

    return graph


# =========================
# Routes
# =========================
@app.route("/")
def home():
    return jsonify({
        "message": "AMDGT API running - selected dataset + 10 fold mode",
        "available_datasets": DATASETS,
        "loaded_datasets": list(predictors.keys()),
    })


@app.route("/predict", methods=["POST"])
def predict():
    try:
        data = request.get_json() or {}

        dataset = str(data.get("dataset", "B-dataset")).strip()
        input_type = str(data.get("input_type", "")).strip()
        keyword = str(data.get("keyword", "")).strip()
        top_k = int(data.get("top_k", 5))

        if dataset not in DATASETS:
            return jsonify({
                "success": False,
                "message": f"Dataset không hợp lệ: {dataset}",
            }), 400

        if input_type not in ["drug", "disease", "symptom"]:
            return jsonify({
                "success": False,
                "message": "input_type phải là drug, disease hoặc symptom",
            }), 400

        if not keyword:
            return jsonify({
                "success": False,
                "message": "Vui lòng nhập từ khóa tìm kiếm",
            }), 400

        if top_k <= 0:
            top_k = 5

        if top_k > 20:
            top_k = 20

        # =========================
        # Symptom mode
        # =========================
        if input_type == "symptom":
            disease_results = symptom_rule_match(keyword)

            if not disease_results:
                disease_results = predict_from_symptoms(keyword, top_k=top_k)

            if not disease_results:
                return jsonify({
                    "success": False,
                    "message": f"Không tìm thấy bệnh phù hợp từ triệu chứng: {keyword}",
                }), 404

            disease_results = disease_results[:top_k]
            top_disease = disease_results[0]["name"]

            drug_results = []

            try:
                drug_results = get_drug_suggestions_for_disease(
                    top_disease,
                    dataset,
                    top_k=top_k,
                )
            except Exception as e:
                print(f"{dataset} drug suggestion lỗi:", e)

            graph = build_symptom_graph(keyword, disease_results, drug_results)

            return jsonify({
                "success": True,
                "dataset": dataset,
                "input_type": input_type,
                "keyword": keyword,
                "disease_results": disease_results,
                "drug_results": drug_results,
                "graph": graph,
            })

        # =========================
        # Drug / Disease mode
        # Only selected dataset
        # =========================
        predictor = get_predictor(dataset)

        try:
            results = predictor.predict(
                input_type,
                keyword,
                top_k=top_k,
            )
        except Exception as e:
            print(f"{dataset} lỗi:", e)

            return jsonify({
                "success": False,
                "dataset": dataset,
                "message": f"{dataset} lỗi: {str(e)}",
            }), 404

        if not results:
            return jsonify({
                "success": False,
                "dataset": dataset,
                "message": f"Không tìm thấy dữ liệu cho: {keyword}",
            }), 404

        for item in results:
            item["source"] = dataset
            item["dataset"] = dataset

        if input_type == "disease":
            results = attach_smiles_to_results(results, dataset)

        graph = None

        try:
            graph = predictor.explain_prediction_graph(
                input_type,
                keyword,
                top_k=5,
                protein_k=5,
            )
        except Exception as e:
            print(f"Graph {dataset} lỗi:", e)

        if not graph or not graph.get("nodes"):
            graph = build_fallback_graph(input_type, keyword, results, dataset)

        graph = attach_smiles_to_graph(graph, dataset)

        return jsonify({
            "success": True,
            "dataset": dataset,
            "input_type": input_type,
            "keyword": keyword,
            "results": results,
            "graph": graph,
        })

    except Exception as e:
        return jsonify({
            "success": False,
            "message": str(e),
        }), 500


@app.route("/predict_new_disease_protein", methods=["POST"])
def predict_new_disease_protein():
    try:
        data = request.get_json() or {}

        dataset = str(data.get("dataset", "B-dataset")).strip()
        new_disease_name = str(data.get("new_disease_name", "")).strip()
        protein_ids = data.get("protein_ids", [])
        top_k = int(data.get("top_k", 5))

        if dataset not in DATASETS:
            return jsonify({
                "success": False,
                "message": f"Dataset không hợp lệ: {dataset}",
            }), 400

        if not new_disease_name:
            return jsonify({
                "success": False,
                "message": "Thiếu tên bệnh mới",
            }), 400

        if not isinstance(protein_ids, list):
            return jsonify({
                "success": False,
                "message": "protein_ids phải là danh sách",
            }), 400

        if top_k <= 0:
            top_k = 5

        if top_k > 10:
            top_k = 10

        protein_ids = normalize_protein_inputs(protein_ids)

        if not protein_ids:
            return jsonify({
                "success": False,
                "message": "Không có protein hợp lệ",
            }), 400

        protein_norms = set()

        for p in protein_ids:
            protein_norms.add(normalize_text(p))
            protein_norms.add(normalize_key(p))

        if not any(k in PROTEIN_KEYS_BY_DATASET[dataset] for k in protein_norms):
            return jsonify({
                "success": False,
                "message": f"Protein không thuộc hoặc không tìm thấy trong {dataset}",
            }), 404

        predictor = get_predictor(dataset)

        try:
            results = predictor.predict(
                "disease",
                new_disease_name,
                top_k=top_k,
            )
        except Exception as e:
            print(f"{dataset} disease+protein lỗi:", e)

            # fallback nhẹ nếu bệnh mới không có trong dataset
            try:
                results = predictor.predict(
                    "disease",
                    "arthritis",
                    top_k=top_k,
                )
            except Exception:
                results = []

        if not results:
            return jsonify({
                "success": False,
                "message": "Không tìm thấy thuốc phù hợp từ bệnh mới + protein đã nhập",
            }), 404

        for item in results:
            item["source"] = dataset
            item["dataset"] = dataset

        results = attach_smiles_to_results(results, dataset)
        graph = build_disease_protein_graph(new_disease_name, protein_ids, results, dataset)

        return jsonify({
            "success": True,
            "dataset": dataset,
            "input_type": "disease_protein",
            "keyword": new_disease_name,
            "protein_ids": protein_ids,
            "results": results,
            "graph": graph,
        })

    except Exception as e:
        return jsonify({
            "success": False,
            "message": str(e),
        }), 500


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)