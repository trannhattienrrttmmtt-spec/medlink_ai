import os
import json
import torch
from generator.smiles_vocab import SmilesVocab
from generator.cvae_model import ConditionalSmilesVAE

print(">>> ĐANG LOAD FILE generator_drug.py MỚI <<<")

BASE = os.path.dirname(os.path.abspath(__file__))
VOCAB_FILE = os.path.join(BASE, "smiles_vocab.json")
DISEASE_FILE = os.path.join(BASE, "disease_index.json")
MODEL_FILE = os.path.join(BASE, "generator_model.pt")

DEVICE = "cuda" if torch.cuda.is_available() else "cpu"


class DrugGenerator:
    def __init__(self):
        self.vocab = SmilesVocab.load(VOCAB_FILE)

        with open(DISEASE_FILE, "r", encoding="utf-8") as f:
            self.disease_to_idx = json.load(f)

        self.idx_to_disease = {v: k for k, v in self.disease_to_idx.items()}

        self.model = ConditionalSmilesVAE(
            vocab_size=len(self.vocab.stoi),
            disease_count=len(self.disease_to_idx),
            emb_dim=128,
            hidden_dim=128,
            latent_dim=32
        ).to(DEVICE)

        self.model.load_state_dict(torch.load(MODEL_FILE, map_location=DEVICE))
        self.model.eval()

    def _normalize_disease(self, disease_name: str) -> str:
        return str(disease_name).strip().lower()

    def _find_closest_disease(self, disease_name: str):
        disease_name = self._normalize_disease(disease_name)

        if disease_name in self.disease_to_idx:
            return disease_name

        for d in self.disease_to_idx.keys():
            dl = d.lower()
            if disease_name in dl or dl in disease_name:
                return d

        return None

    def generate(self, disease_name, n=5):
        disease_name = self._normalize_disease(disease_name)

        print("Disease nhận vào:", disease_name)
        print("Tổng số disease trong model:", len(self.disease_to_idx))

        matched_disease = self._find_closest_disease(disease_name)

        if not matched_disease:
            print("Không tìm thấy disease trong disease_index.json")
            return []

        if matched_disease != disease_name:
            print(f"Map disease: {disease_name} -> {matched_disease}")

        d_idx = self.disease_to_idx[matched_disease]
        disease_ids = torch.tensor([d_idx] * n, dtype=torch.long, device=DEVICE)

        start_id = self.vocab.stoi[self.vocab.start_token]

        with torch.no_grad():
            out = self.model.generate(disease_ids, start_id, max_len=120)

        smiles_list = []
        for seq in out:
            tokens = seq.tolist()
            print("RAW:", tokens)

            smiles = self.vocab.decode(tokens)
            smiles = (
                smiles.replace("<PAD>", "")
                .replace("<SOS>", "")
                .replace("<EOS>", "")
                .replace("<UNK>", "")
                .strip()
            )

            print("DECODE:", smiles)

            if smiles and len(smiles) > 2:
                smiles_list.append(smiles)

        uniq = []
        seen = set()
        for s in smiles_list:
            if s not in seen:
                uniq.append(s)
                seen.add(s)

        print("SMILES cuối cùng:", uniq[:n])
        return uniq[:n]