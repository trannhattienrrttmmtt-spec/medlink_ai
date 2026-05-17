import torch
import torch.nn as nn
import torch.nn.functional as F
import dgl

from model.graph_transformer_layer import GraphTransformerLayer


class GraphTransformer(nn.Module):
    def __init__(self, device, n_layers, node_dim, hidden_dim, out_dim, n_heads, dropout):
        super(GraphTransformer, self).__init__()

        self.device = device
        self.layer_norm = True
        self.batch_norm = False
        self.residual = True

        self.linear_h = nn.Linear(node_dim, hidden_dim)

        self.layers = nn.ModuleList([
            GraphTransformerLayer(
                hidden_dim,
                hidden_dim,
                n_heads,
                dropout,
                self.layer_norm,
                self.batch_norm,
                self.residual
            )
            for _ in range(n_layers - 1)
        ])

        self.layers.append(
            GraphTransformerLayer(
                hidden_dim,
                out_dim,
                n_heads,
                dropout,
                self.layer_norm,
                self.batch_norm,
                self.residual
            )
        )

    def forward(self, g):
        # Lấy device thật của model sau khi model.to(cuda/cpu)
        device = self.linear_h.weight.device

        # Đưa graph và node feature sang đúng device
        g = g.to(device)

        if "drs" not in g.ndata:
            raise KeyError("gt_net_drug.py: Không tìm thấy g.ndata['drs']")

        h = g.ndata["drs"].float().to(device)

        h = self.linear_h(h)

        for conv in self.layers:
            h = conv(g, h)

        g.ndata["h"] = h

        return h