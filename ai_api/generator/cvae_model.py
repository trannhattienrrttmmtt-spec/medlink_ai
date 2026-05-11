import torch
import torch.nn as nn
import torch.nn.functional as F


class ConditionalSmilesVAE(nn.Module):
    def __init__(self, vocab_size, disease_count, emb_dim=128, hidden_dim=128, latent_dim=32):
        super().__init__()
        self.latent_dim = latent_dim
        self.vocab_size = vocab_size

        self.token_emb = nn.Embedding(vocab_size, emb_dim)
        self.disease_emb = nn.Embedding(disease_count, emb_dim)

        self.encoder = nn.GRU(
            input_size=emb_dim,
            hidden_size=hidden_dim,
            batch_first=True
        )

        self.fc_mu = nn.Linear(hidden_dim + emb_dim, latent_dim)
        self.fc_logvar = nn.Linear(hidden_dim + emb_dim, latent_dim)

        self.decoder_input = nn.Linear(latent_dim + emb_dim, hidden_dim)

        self.decoder = nn.GRU(
            input_size=emb_dim,
            hidden_size=hidden_dim,
            batch_first=True
        )

        self.out = nn.Linear(hidden_dim, vocab_size)

    def encode(self, x, disease_ids):
        x_emb = self.token_emb(x)
        _, h = self.encoder(x_emb)
        h = h[-1]

        d_emb = self.disease_emb(disease_ids)
        h_cat = torch.cat([h, d_emb], dim=-1)

        mu = self.fc_mu(h_cat)
        logvar = self.fc_logvar(h_cat)
        return mu, logvar

    def reparameterize(self, mu, logvar):
        std = torch.exp(0.5 * logvar)
        eps = torch.randn_like(std)
        return mu + eps * std

    def decode(self, z, disease_ids, x_in):
        d_emb = self.disease_emb(disease_ids)
        h0 = self.decoder_input(torch.cat([z, d_emb], dim=-1)).unsqueeze(0)

        x_emb = self.token_emb(x_in)
        out, _ = self.decoder(x_emb, h0)
        logits = self.out(out)
        return logits

    def forward(self, x, disease_ids):
        mu, logvar = self.encode(x, disease_ids)
        z = self.reparameterize(mu, logvar)
        logits = self.decode(z, disease_ids, x)
        return logits, mu, logvar

    def generate(self, disease_ids, start_token_id, max_len=120):
        batch_size = disease_ids.size(0)

        d_emb = self.disease_emb(disease_ids)

        # tăng độ ngẫu nhiên cho latent để giảm collapse
        z = torch.randn(batch_size, self.latent_dim, device=disease_ids.device) * 1.5

        h = self.decoder_input(torch.cat([z, d_emb], dim=-1)).unsqueeze(0)

        current = torch.full(
            (batch_size, 1),
            start_token_id,
            dtype=torch.long,
            device=disease_ids.device
        )

        outputs = []

        for step in range(max_len):
            x_emb = self.token_emb(current[:, -1:])
            out, h = self.decoder(x_emb, h)

            logits = self.out(out[:, -1, :])

            # temperature để tăng đa dạng
            temperature = 1.5
            logits = logits / temperature

            probs = torch.softmax(logits, dim=-1)

            # chặn token đặc biệt
            probs[:, 0] = 0.0  # <PAD>
            probs[:, 1] = 0.0  # <SOS>probs[:, 3] = 0.0  # <UNK>

            # chưa cho kết thúc quá sớm
            if step < 5:
                probs[:, 2] = 0.0  # <EOS>

            # chuẩn hóa lại
            probs_sum = probs.sum(dim=-1, keepdim=True)
            probs_sum[probs_sum == 0] = 1.0
            probs = probs / probs_sum

            # top-k sampling
            top_k = 5
            top_probs, top_idx = torch.topk(probs, top_k, dim=-1)

            # thêm noise nhẹ chống collapse
            top_probs = top_probs + torch.rand_like(top_probs) * 0.05
            top_probs = top_probs / top_probs.sum(dim=-1, keepdim=True)

            sampled = torch.multinomial(top_probs, 1)
            next_token = top_idx.gather(-1, sampled)

            # chống lặp 1 token quá mức
            if len(outputs) > 5:
                same_as_prev = (next_token == outputs[-1]).all()
                if same_as_prev:
                    next_token = torch.randint(
                        low=4,
                        high=self.vocab_size,
                        size=(batch_size, 1),
                        device=current.device
                    )

            outputs.append(next_token)
            current = torch.cat([current, next_token], dim=1)

        return torch.cat(outputs, dim=1)


def vae_loss(logits, target, mu, logvar, pad_idx):
    recon_loss = F.cross_entropy(
        logits.reshape(-1, logits.size(-1)),
        target.reshape(-1),
        ignore_index=pad_idx
    )

    kld = -0.5 * torch.mean(1 + logvar - mu.pow(2) - logvar.exp())

    # tăng KL để đỡ mode collapse
    total = recon_loss + 1.0 * kld
    return total, recon_loss, kld