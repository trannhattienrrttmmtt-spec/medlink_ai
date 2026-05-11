import os
import json
import pandas as pd
import torch
import torch.nn.functional as F
from torch.utils.data import Dataset, DataLoader

from generator.smiles_vocab import SmilesVocab
from generator.cvae_model import ConditionalSmilesVAE

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

DATA_FILE = os.path.join(BASE_DIR, "generator_dataset.csv")
VOCAB_FILE = os.path.join(BASE_DIR, "smiles_vocab.json")
DISEASE_FILE = os.path.join(BASE_DIR, "disease_index.json")
MODEL_FILE = os.path.join(BASE_DIR, "generator_model.pt")

MAX_LEN = 120
BATCH_SIZE = 32
EPOCHS = 10
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"


class GeneratorDataset(Dataset):
    def __init__(self, df, vocab, disease_to_idx):
        self.df = df.reset_index(drop=True)
        self.vocab = vocab
        self.disease_to_idx = disease_to_idx

    def __len__(self):
        return len(self.df)

    def __getitem__(self, idx):
        row = self.df.iloc[idx]
        x = self.vocab.encode(str(row["smiles"]), MAX_LEN)
        d = self.disease_to_idx[str(row["disease_name"])]
        return torch.tensor(x, dtype=torch.long), torch.tensor(d, dtype=torch.long)


def word_dropout(x, drop_prob=0.3, unk_idx=3, pad_idx=0):
    x = x.clone()
    mask = (torch.rand_like(x.float()) < drop_prob) & (x != pad_idx)
    x[mask] = unk_idx
    return x


def main():
    if not os.path.exists(DATA_FILE):
        raise FileNotFoundError("Chưa có generator_dataset.csv. Hãy chạy preprocess_generator.py trước.")

    df = pd.read_csv(DATA_FILE).dropna()

    smiles_list = df["smiles"].astype(str).tolist()
    vocab = SmilesVocab(smiles_list)
    vocab.save(VOCAB_FILE)

    diseases = sorted(df["disease_name"].astype(str).unique().tolist())
    disease_to_idx = {d: i for i, d in enumerate(diseases)}

    with open(DISEASE_FILE, "w", encoding="utf-8") as f:
        json.dump(disease_to_idx, f, ensure_ascii=False)

    dataset = GeneratorDataset(df, vocab, disease_to_idx)
    loader = DataLoader(dataset, batch_size=BATCH_SIZE, shuffle=True)

    model = ConditionalSmilesVAE(
        vocab_size=len(vocab.stoi),
        disease_count=len(disease_to_idx),
        emb_dim=128,
        hidden_dim=128,
        latent_dim=32
    ).to(DEVICE)

    optimizer = torch.optim.Adam(model.parameters(), lr=1e-4)

    pad_idx = vocab.stoi[vocab.pad_token]
    best_loss = float("inf")

    for epoch in range(EPOCHS):
        model.train()
        total_loss = 0.0
        total_recon = 0.0
        total_kld = 0.0

        # KL annealing
        beta = min(1.0, (epoch + 1) / 5.0)

        for x, disease_ids in loader:
            x = x.to(DEVICE)
            disease_ids = disease_ids.to(DEVICE)

            # word dropout để ép model dùng latent z
            x_input = word_dropout(
                x,
                drop_prob=0.3,
                unk_idx=vocab.stoi.get("<UNK>", 3),
                pad_idx=pad_idx
            )

            logits, mu, logvar = model(x_input, disease_ids)
            recon_loss = F.cross_entropy(
                logits.reshape(-1, logits.size(-1)),
                x.reshape(-1),
                ignore_index=pad_idx,
                label_smoothing=0.1
            )

            kld = -0.5 * torch.mean(
                1 + logvar - mu.pow(2) - logvar.exp()
            )

            loss = recon_loss + beta * kld

            if torch.isnan(loss):
                print("Loss bị NaN, dừng train.")
                return

            optimizer.zero_grad()
            loss.backward()

            # gradient clipping chống học quá nhanh
            torch.nn.utils.clip_grad_norm_(model.parameters(), 1.0)

            optimizer.step()

            total_loss += loss.item()
            total_recon += recon_loss.item()
            total_kld += kld.item()

        avg_loss = total_loss / max(len(loader), 1)
        avg_recon = total_recon / max(len(loader), 1)
        avg_kld = total_kld / max(len(loader), 1)

        print(
            f"Epoch {epoch+1}/{EPOCHS} - "
            f"loss={avg_loss:.6f} - "
            f"recon={avg_recon:.6f} - "
            f"kld={avg_kld:.6f} - "
            f"beta={beta:.2f}"
        )

        if avg_loss < best_loss:
            best_loss = avg_loss
            torch.save(model.state_dict(), MODEL_FILE)
            print("Đã lưu model tốt nhất:", MODEL_FILE)

        # dừng sớm nếu loss tụt quá bất thường
        if epoch >= 1 and avg_loss < 0.01:
            print("Loss xuống quá thấp quá sớm, dừng để tránh collapse.")
            break

    print("Train xong.")
    print("VOCAB:", VOCAB_FILE)
    print("DISEASE:", DISEASE_FILE)
    print("MODEL:", MODEL_FILE)


if __name__ == "__main__":
    main()