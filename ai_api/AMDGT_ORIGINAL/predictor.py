import os
import sys
import importlib
import importlib.util
import torch
import numpy as np
import pandas as pd

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
AMDGT_DIR = BASE_DIR

# === Force import từ AMDGT_ORIGINAL/model/ thay vì AMDGT/model/ ===
# Xóa cached modules "model.*" có thể đã import từ AMDGT/
_to_remove = [k for k in sys.modules if k == "model" or k.startswith("model.")]
for k in _to_remove:
    del sys.modules[k]

# Cũng xóa data_preprocess nếu cached
if "data_preprocess" in sys.modules:
    del sys.modules["data_preprocess"]

# Đưa AMDGT_ORIGINAL lên đầu sys.path
if AMDGT_DIR in sys.path:
    sys.path.remove(AMDGT_DIR)
sys.path.insert(0, AMDGT_DIR)

from model.AMNTDDA import AMNTDDA
from data_preprocess import (
    get_data,
    data_processing,
    k_fold,
    dgl_similarity_graph,
    dgl_heterograph
)

# Verify đúng file
assert "AMDGT_ORIGINAL" in AMNTDDA.__module__ or "AMDGT_ORIGINAL" in str(
    sys.modules.get("model.AMNTDDA", object).__dict__.get("__file__", "")
) or os.path.join("AMDGT_ORIGINAL", "model") in str(
    getattr(sys.modules.get("model.AMNTDDA"), "__file__", "")
), f"AMNTDDA imported from wrong location: {getattr(sys.modules.get('model.AMNTDDA'), '__file__', 'unknown')}"

print(f"[AMDGT_ORIGINAL] AMNTDDA loaded from: {sys.modules['model.AMNTDDA'].__file__}")


def standardize(x: torch.Tensor) -> torch.Tensor:
    mean = x.mean(dim=0, keepdim=True)
    std = x.std(dim=0, keepdim=True)
    std[std < 1e-8] = 1.0
    return (x - mean) / std


def normalize_top_scores(probs, top_indices):
    top_values = probs[top_indices]
    min_v = float(np.min(top_values))
    max_v = float(np.max(top_values))

    if max_v - min_v < 1e-8:
        return np.ones_like(top_values) * 0.75

    # Map vào range [0.40, 0.97] — spread rộng hơn
    normalized = (top_values - min_v) / (max_v - min_v)
    return 0.40 + 0.57 * normalized


class AMDGTPredictor:
    def __init__(self, dataset="B-dataset"):
        self.base_dir = BASE_DIR
        self.amdgt_dir = AMDGT_DIR
        self.dataset = dataset
        self.device = torch.device("cuda" if torch.cuda.is_available() else "cpu")

        self.args = self._build_args()
        self.models = []

        self.data = None
        self.drdr_graph = None
        self.didi_graph = None
        self.drug_feature = None
        self.disease_feature = None
        self.protein_feature = None

        self.drug_names = []
        self.disease_names = []
        self.protein_names = []

        self.raw_disease_nodes = []
        self.raw_drug_nodes = []
        self.raw_protein_nodes = []

        self._prepare_data()
        self._load_names()
        self._load_models()
        self._cache_graphs()

    def _build_args(self):
        class Args:
            pass

        args = Args()

        args.k_fold = 10
        args.epochs = 1000
        args.lr = 0.0001
        args.weight_decay = 0.001
        args.random_seed = 2024
        args.negative_rate = 1.0
        args.dataset = self.dataset
        args.dropout = 0.2

        args.gt_layer = 2
        args.gt_head = 4

        args.hgt_layer = 2
        args.hgt_head = 8

        args.tr_layer = 2
        args.tr_head = 4

        # =====================================================
        # CẤU HÌNH ĐÚNG THEO CHECKPOINT + AMNTDDA.py
        #
        # B-dataset:
        #   neighbor = 3
        #   GT output = 512
        #   HGT input = 64
        #   HGT last output = hgt_head_dim * hgt_head = 64 * 8 = 512
        #
        # C/F-dataset:
        #   neighbor = 5
        #   GT output = 256
        #   HGT input = 64
        #   HGT last output = hgt_head_dim * hgt_head = 32 * 8 = 256
        # =====================================================

        if self.dataset == "B-dataset":
            args.neighbor = 3
            args.gt_out_dim = 200
            args.hgt_in_dim = 64
            args.hgt_out_dim = 64
            args.hgt_head_dim = 25  # hgt_dgl_last output per head, total = 25*8 = 200 = gt_out_dim

        elif self.dataset == "C-dataset":
            args.neighbor = 5
            args.gt_out_dim = 200
            args.hgt_in_dim = 64
            args.hgt_out_dim = 64
            args.hgt_head_dim = 25

        elif self.dataset == "F-dataset":
            args.neighbor = 5
            args.gt_out_dim = 200
            args.hgt_in_dim = 64
            args.hgt_out_dim = 64
            args.hgt_head_dim = 25

        else:
            raise ValueError(f"Dataset không hợp lệ: {self.dataset}")

        args.data_dir = os.path.join(BASE_DIR, "data", args.dataset) + os.sep
        args.result_dir = os.path.join(BASE_DIR, "Result", args.dataset) + os.sep
        args.device = self.device

        return args

    def _prepare_data(self):
        data = get_data(self.args)

        self.args.drug_number = data["drug_number"]
        self.args.disease_number = data["disease_number"]
        self.args.protein_number = data["protein_number"]

        data = data_processing(data, self.args)
        data = k_fold(data, self.args)

        drdr_graph, didi_graph, data = dgl_similarity_graph(data, self.args)

        self.data = data
        self.drdr_graph = drdr_graph.to(self.device)
        self.didi_graph = didi_graph.to(self.device)

        self.drug_feature = torch.FloatTensor(data["drugfeature"]).to(self.device)
        self.disease_feature = torch.FloatTensor(data["diseasefeature"]).to(self.device)
        self.protein_feature = torch.FloatTensor(data["proteinfeature"]).to(self.device)

        self.drug_feature = standardize(self.drug_feature)
        self.disease_feature = standardize(self.disease_feature)
        self.protein_feature = standardize(self.protein_feature)

        self.args.drug_in_dim = self.drug_feature.shape[1]
        self.args.disease_in_dim = self.disease_feature.shape[1]
        self.args.protein_in_dim = self.protein_feature.shape[1]

        # hgt_in_dim = 64 (set trong _build_args), khớp với checkpoint
        # drug_linear: 300->64, protein_linear: 320->64, disease: 64 (no linear)

        print(f"[{self.dataset}] device = {self.device}")
        print(f"[{self.dataset}] topology_features.py = OFF")
        print(f"[{self.dataset}] neighbor = {self.args.neighbor}")
        print(f"[{self.dataset}] gt_out_dim = {self.args.gt_out_dim}")
        print(f"[{self.dataset}] hgt_out_dim = {self.args.hgt_out_dim}")
        print(f"[{self.dataset}] hgt_head = {self.args.hgt_head}")
        print(f"[{self.dataset}] hgt_head_dim = {self.args.hgt_head_dim}")
        print(f"[{self.dataset}] hgt_last_output = {self.args.hgt_head * self.args.hgt_head_dim}")
        print(f"[{self.dataset}] drug_in_dim = {self.args.drug_in_dim}")
        print(f"[{self.dataset}] disease_in_dim = {self.args.disease_in_dim}")
        print(f"[{self.dataset}] protein_in_dim = {self.args.protein_in_dim}")
        print(f"[{self.dataset}] hgt_in_dim = {self.args.hgt_in_dim}")

    def _load_models(self):
        self.models = []

        model_dirs = [
            os.path.join(self.amdgt_dir, "Result", self.dataset, "AMNTDDA"),
            os.path.join(self.amdgt_dir, "Result", self.dataset),
        ]

        possible_paths = []

        for model_dir in model_dirs:
            for fold in range(10):
                possible_paths.append(os.path.join(model_dir, f"best_fold_{fold}.pt"))

            for fold in range(1, 11):
                possible_paths.append(os.path.join(model_dir, f"best_fold_{fold}.pt"))

        saved_model_dir = os.path.join(self.amdgt_dir, "saved_model")

        for fold in range(10):
            possible_paths.append(
                os.path.join(saved_model_dir, f"{self.dataset}_amdgt_fold_{fold}.pth")
            )

        for fold in range(1, 11):
            possible_paths.append(
                os.path.join(saved_model_dir, f"{self.dataset}_amdgt_fold_{fold}.pth")
            )

        loaded_paths = set()

        for model_path in possible_paths:
            if model_path in loaded_paths:
                continue

            if not os.path.exists(model_path):
                continue

            try:
                model = AMNTDDA(self.args).to(self.device)
                checkpoint = torch.load(model_path, map_location=self.device)

                if isinstance(checkpoint, dict) and "model_state_dict" in checkpoint:
                    state_dict = checkpoint["model_state_dict"]
                else:
                    state_dict = checkpoint

                # Nên strict=True để biết model và code có khớp thật không.
                model.load_state_dict(state_dict, strict=True)
                model.eval()

                self.models.append(model)
                loaded_paths.add(model_path)

                print(f"[{self.dataset}] loaded model: {model_path}")

            except Exception as e:
                print(f"[{self.dataset}] cannot load model: {model_path}")
                print(f"[{self.dataset}] reason: {e}")

        if not self.models:
            raise FileNotFoundError(
                f"Không load được model nào cho {self.dataset}. "
                f"Kiểm tra model trong: "
                f"{os.path.join(self.amdgt_dir, 'Result', self.dataset, 'AMNTDDA')}"
            )

        print(f"[{self.dataset}] total loaded models = {len(self.models)}")

    def _cache_graphs(self):
        self._cached_graphs = {}
        fold_index = 0
        if fold_index < len(self.data["X_train"]):
            x_train = self.data["X_train"][fold_index]
            drdipr_graph, _ = dgl_heterograph(self.data, x_train, self.args)
            self._cached_graphs[fold_index] = drdipr_graph.to(self.device)
        print(f"[{self.dataset}] cached graphs for {len(self._cached_graphs)} folds")

        # Pre-compute toàn bộ score matrix
        self._precompute_all()

    def _get_graph(self, fold_index=0):
        if fold_index in self._cached_graphs:
            return self._cached_graphs[fold_index]
        x_train = self.data["X_train"][fold_index]
        drdipr_graph, _ = dgl_heterograph(self.data, x_train, self.args)
        drdipr_graph = drdipr_graph.to(self.device)
        self._cached_graphs[fold_index] = drdipr_graph
        return drdipr_graph

    def _precompute_all(self):
        import time

        cache_file = os.path.join(self.amdgt_dir, "Result", self.dataset, "score_matrix_original.npy")
        if os.path.exists(cache_file):
            self._score_matrix = np.load(cache_file)
            print(f"[{self.dataset}] Loaded pre-computed matrix from {cache_file} ({self._score_matrix.shape})")
            return

        print(f"[{self.dataset}] Computing score matrix original (first time only)...")
        start = time.time()

        drdipr_graph = self._get_graph(0)
        drug_num = self.args.drug_number
        disease_num = self.args.disease_number

        all_pairs = []
        for d in range(drug_num):
            for di in range(disease_num):
                all_pairs.append([d, di])

        x_all = torch.LongTensor(all_pairs).to(self.device)

        all_probs = []
        with torch.no_grad():
            batch_size = 10000
            for i in range(0, len(x_all), batch_size):
                batch = x_all[i:i+batch_size]
                batch_probs = []
                for model in self.models:
                    _, scores = model(
                        self.drdr_graph,
                        self.didi_graph,
                        drdipr_graph,
                        self.drug_feature,
                        self.disease_feature,
                        self.protein_feature,
                        batch
                    )
                    probs = torch.softmax(scores / 2.0, dim=-1)[:, 1]
                    batch_probs.append(probs)
                avg = torch.stack(batch_probs, dim=0).mean(dim=0)
                all_probs.append(avg)

        full_probs = torch.cat(all_probs, dim=0).cpu().numpy()
        self._score_matrix = full_probs.reshape(drug_num, disease_num)

        os.makedirs(os.path.dirname(cache_file), exist_ok=True)
        np.save(cache_file, self._score_matrix)

        elapsed = time.time() - start
        print(f"[{self.dataset}] Done in {elapsed:.1f}s. Saved to {cache_file}")

    def _ensemble_predict_scores(self, drdipr_graph, x_input, temperature=2.0):
        all_probs = []

        with torch.no_grad():
            for model in self.models:
                _, scores = model(
                    self.drdr_graph,
                    self.didi_graph,
                    drdipr_graph,
                    self.drug_feature,
                    self.disease_feature,
                    self.protein_feature,
                    x_input
                )

                probs = torch.softmax(scores / temperature, dim=-1)[:, 1]
                all_probs.append(probs)

        avg_probs = torch.stack(all_probs, dim=0).mean(dim=0)
        return avg_probs.cpu().numpy()

    def _read_best_name_column(self, df):
        possible_columns = [
            "name",
            "Name",
            "drug_name",
            "DrugName",
            "drug",
            "Drug",
            "disease",
            "Disease"
        ]

        for col in possible_columns:
            if col in df.columns:
                return df[col].astype(str).fillna("").tolist()

        if df.shape[1] >= 2:
            return df.iloc[:, 1].astype(str).fillna("").tolist()

        return df.iloc[:, 0].astype(str).fillna("").tolist()

    def _find_existing_file(self, *candidates):
        for path in candidates:
            if os.path.exists(path):
                return path
        return None

    def _is_code_name(self, text):
        if not text:
            return False

        t = str(text).strip().upper()

        return (
            t.startswith("DB")
            or t.startswith("D")
            or t.startswith("DISEASE_")
            or t.startswith("DRUG_")
        )

    def _load_names(self):
        drug_info_path = self._find_existing_file(
            os.path.join(self.amdgt_dir, "data", self.dataset, "DrugInformation.csv")
        )

        allnode_path = self._find_existing_file(
            os.path.join(self.amdgt_dir, "data", self.dataset, "Allnode.csv"),
            os.path.join(self.amdgt_dir, "data", self.dataset, "AllNode.csv")
        )

        if not drug_info_path:
            raise FileNotFoundError(
                f"Không tìm thấy DrugInformation.csv trong {self.dataset}"
            )

        if not allnode_path:
            raise FileNotFoundError(
                f"Không tìm thấy Allnode.csv hoặc AllNode.csv trong {self.dataset}"
            )

        df_drug = pd.read_csv(drug_info_path)
        self.drug_names = [
            str(x).strip()
            for x in self._read_best_name_column(df_drug)
        ]
        self.drug_names = self.drug_names[:self.args.drug_number]
        self.raw_drug_nodes = self.drug_names[:]

        df_node = pd.read_csv(allnode_path, header=None)
        all_names = [
            str(x).strip()
            for x in self._read_best_name_column(df_node)
        ]

        drug_num = self.args.drug_number
        disease_num = self.args.disease_number
        protein_num = self.args.protein_number

        self.raw_disease_nodes = all_names[drug_num:drug_num + disease_num]
        self.disease_names = self.raw_disease_nodes[:]

        protein_start = drug_num + disease_num
        self.raw_protein_nodes = all_names[protein_start:protein_start + protein_num]
        self.protein_names = self.raw_protein_nodes[:]

        if len(self.drug_names) < self.args.drug_number:
            self.drug_names += [
                f"Drug_{i}"
                for i in range(len(self.drug_names), self.args.drug_number)
            ]

        if len(self.disease_names) < self.args.disease_number:
            self.disease_names += [
                f"Disease_{i}"
                for i in range(len(self.disease_names), self.args.disease_number)
            ]

        if len(self.protein_names) < self.args.protein_number:
            self.protein_names += [
                f"Protein_{i}"
                for i in range(len(self.protein_names), self.args.protein_number)
            ]

        print(f"[{self.dataset}] drug_number = {drug_num}")
        print(f"[{self.dataset}] disease_number = {disease_num}")
        print(f"[{self.dataset}] protein_number = {protein_num}")
        print(f"[{self.dataset}] sample drug names = {self.drug_names[:10]}")
        print(f"[{self.dataset}] sample disease names = {self.disease_names[:10]}")
        print(f"[{self.dataset}] sample protein names = {self.protein_names[:10]}")

    def _normalize_text(self, text):
        return " ".join(
            str(text)
            .strip()
            .lower()
            .replace(",", " ")
            .replace("_", " ")
            .replace("-", " ")
            .split()
        )

    def _find_index_by_name(self, names, keyword):
        synonym_map = {
            "paracetamol": "acetaminophen",
            "tylenol": "acetaminophen",
            "aspirin": "acetylsalicylic acid",
            "acetylsalicylic": "acetylsalicylic acid"
        }

        keyword = self._normalize_text(keyword)

        for i, name in enumerate(names):
            if self._normalize_text(name) == keyword:
                return i

        for i, name in enumerate(names):
            normalized_name = self._normalize_text(name)
            if keyword in normalized_name:
                return i

        keyword_alias = synonym_map.get(keyword)
        if keyword_alias and keyword_alias != keyword:
            for i, name in enumerate(names):
                if self._normalize_text(name) == keyword_alias:
                    return i

            for i, name in enumerate(names):
                normalized_name = self._normalize_text(name)
                if keyword_alias in normalized_name:
                    return i

        return None

    def _build_result_item(self, idx, score, input_type):
        idx = int(idx)
        score = float(score)

        if input_type == "drug":
            raw_value = (
                self.raw_disease_nodes[idx]
                if idx < len(self.raw_disease_nodes)
                else f"Disease_{idx}"
            )
        else:
            raw_value = (
                self.raw_drug_nodes[idx]
                if idx < len(self.raw_drug_nodes)
                else f"Drug_{idx}"
            )

        return {
            "index": idx,
            "name": raw_value,
            "code": raw_value if self._is_code_name(raw_value) else None,
            "score": score,
            "dataset": self.dataset
        }

    def predict(self, input_type, keyword, top_k=5, fold_index=0):
        if input_type not in ["drug", "disease"]:
            raise ValueError("input_type phải là drug hoặc disease")

        drug_num = self.args.drug_number
        disease_num = self.args.disease_number

        if input_type == "drug":
            drug_idx = self._find_index_by_name(self.drug_names, keyword)
            if drug_idx is None:
                raise ValueError(f"Không tìm thấy thuốc: {keyword}")

            probs = self._score_matrix[drug_idx, :]
            top_indices = np.argsort(-probs)[:top_k]
            display_scores = normalize_top_scores(probs, top_indices)

            return [
                self._build_result_item(idx, display_scores[i], "drug")
                for i, idx in enumerate(top_indices)
            ]

        disease_idx = self._find_index_by_name(self.disease_names, keyword)
        if disease_idx is None:
            raise ValueError(f"Không tìm thấy bệnh: {keyword}")

        probs = self._score_matrix[:, disease_idx]
        top_indices = np.argsort(-probs)[:top_k]
        display_scores = normalize_top_scores(probs, top_indices)

        return [
            self._build_result_item(idx, display_scores[i], "disease")
            for i, idx in enumerate(top_indices)
        ]

    def explain_prediction_graph(
        self,
        input_type,
        keyword,
        top_k=5,
        protein_k=5,
        fold_index=0
    ):
        if input_type not in ["drug", "disease"]:
            raise ValueError("input_type phải là drug hoặc disease")

        if fold_index < 0 or fold_index >= len(self.data["X_train"]):
            fold_index = 0

        x_train = self.data["X_train"][fold_index]
        drdipr_graph, _ = dgl_heterograph(self.data, x_train, self.args)
        drdipr_graph = drdipr_graph.to(self.device)

        drug_num = self.args.drug_number
        disease_num = self.args.disease_number

        nodes = []
        edges = []
        added_nodes = set()
        added_edges = set()

        def add_node(node_id, label, node_type, score=None):
            if node_id in added_nodes:
                return

            added_nodes.add(node_id)

            nodes.append({
                "id": node_id,
                "label": label,
                "type": node_type,
                "score": score
            })

        def add_edge(source, target):
            key = f"{source}->{target}"

            if key in added_edges:
                return

            added_edges.add(key)

            edges.append({
                "from": source,
                "to": target
            })

        if input_type == "drug":
            drug_idx = self._find_index_by_name(self.drug_names, keyword)

            if drug_idx is None:
                raise ValueError(f"Không tìm thấy thuốc: {keyword}")

            pairs = [[drug_idx, d] for d in range(disease_num)]
            x_input = torch.LongTensor(pairs).to(self.device)

            probs = self._ensemble_predict_scores(drdipr_graph, x_input)
            top_indices = np.argsort(-probs)[:top_k]
            display_scores = normalize_top_scores(probs, top_indices)

            input_label = (
                self.drug_names[drug_idx]
                if drug_idx < len(self.drug_names)
                else keyword
            )

            add_node("drug_input", input_label, "drug", score=None)

            for i, idx in enumerate(top_indices):
                label = (
                    self.disease_names[idx]
                    if idx < len(self.disease_names)
                    else f"Disease_{idx}"
                )

                node_id = f"disease_{idx}"

                add_node(
                    node_id,
                    label,
                    "disease",
                    score=float(display_scores[i])
                )

                add_edge("drug_input", node_id)

        else:
            disease_idx = self._find_index_by_name(self.disease_names, keyword)

            if disease_idx is None:
                raise ValueError(f"Không tìm thấy bệnh: {keyword}")

            pairs = [[d, disease_idx] for d in range(drug_num)]
            x_input = torch.LongTensor(pairs).to(self.device)

            probs = self._ensemble_predict_scores(drdipr_graph, x_input)
            top_indices = np.argsort(-probs)[:top_k]
            display_scores = normalize_top_scores(probs, top_indices)

            input_label = (
                self.disease_names[disease_idx]
                if disease_idx < len(self.disease_names)
                else keyword
            )

            add_node("disease_input", input_label, "disease", score=None)

            for i, idx in enumerate(top_indices):
                label = (
                    self.drug_names[idx]
                    if idx < len(self.drug_names)
                    else f"Drug_{idx}"
                )

                node_id = f"drug_{idx}"

                add_node(
                    node_id,
                    label,
                    "drug",
                    score=float(display_scores[i])
                )

                add_edge(node_id, "disease_input")

        return {
            "dataset": self.dataset,
            "nodes": nodes,
            "edges": edges
        }
