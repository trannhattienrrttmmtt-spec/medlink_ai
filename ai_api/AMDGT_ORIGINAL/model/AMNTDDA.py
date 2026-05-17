import dgl
import dgl.nn.pytorch
import torch
import torch.nn as nn
from model import gt_net_drug, gt_net_disease

device = torch.device('cpu')


class AMNTDDA(nn.Module):
    def __init__(self, args):
        super(AMNTDDA, self).__init__()
        self.args = args
        print(f"[AMNTDDA ORIGINAL] Loading from: {__file__}")
        print(f"[AMNTDDA ORIGINAL] gt_out_dim={args.gt_out_dim}, hgt_in_dim={args.hgt_in_dim}, hgt_head_dim={args.hgt_head_dim}")

        # drug: 300 -> hgt_in_dim (64)
        self.drug_linear = nn.Linear(300, args.hgt_in_dim)
        # protein: 320 -> hgt_in_dim (64)
        self.protein_linear = nn.Linear(320, args.hgt_in_dim)
        # disease: 64 dim = hgt_in_dim, no linear needed

        self.gt_drug = gt_net_drug.GraphTransformer(
            device,
            args.gt_layer,
            args.drug_number,
            args.gt_out_dim,
            args.gt_out_dim,
            args.gt_head,
            args.dropout
        )

        self.gt_disease = gt_net_disease.GraphTransformer(
            device,
            args.gt_layer,
            args.disease_number,
            args.gt_out_dim,
            args.gt_out_dim,
            args.gt_head,
            args.dropout
        )

        # HGT: num_ntypes=3, num_etypes=3
        # hgt_dgl: in=64, out_per_head=64/8=8, heads=8 -> output=64
        self.hgt_dgl = dgl.nn.pytorch.conv.HGTConv(
            args.hgt_in_dim,
            int(args.hgt_in_dim / args.hgt_head),
            args.hgt_head,
            3,
            3,
            args.dropout
        )

        # hgt_dgl_last: in=64, out_per_head=hgt_head_dim=25, heads=8 -> output=200=gt_out_dim
        self.hgt_dgl_last = dgl.nn.pytorch.conv.HGTConv(
            args.hgt_in_dim,
            args.hgt_head_dim,
            args.hgt_head,
            3,
            3,
            args.dropout
        )

        self.hgt = nn.ModuleList()
        for _ in range(args.hgt_layer - 1):
            self.hgt.append(self.hgt_dgl)
        self.hgt.append(self.hgt_dgl_last)

        # Full Transformer (encoder + decoder, 3 layers each)
        self.drug_tr = nn.Transformer(
            d_model=args.gt_out_dim,
            nhead=args.tr_head,
            num_encoder_layers=3,
            num_decoder_layers=3,
            batch_first=True
        )

        self.disease_tr = nn.Transformer(
            d_model=args.gt_out_dim,
            nhead=args.tr_head,
            num_encoder_layers=3,
            num_decoder_layers=3,
            batch_first=True
        )

        # TransformerEncoder (2 layers) - used in forward
        encoder_layer = nn.TransformerEncoderLayer(
            d_model=args.gt_out_dim,
            nhead=args.tr_head,
            batch_first=True
        )
        self.drug_trans = nn.TransformerEncoder(encoder_layer, num_layers=args.tr_layer)
        self.disease_trans = nn.TransformerEncoder(encoder_layer, num_layers=args.tr_layer)

        # MLP: input = gt_out_dim * 2 (dr and di concatenated via element-wise mul)
        self.mlp = nn.Sequential(
            nn.Linear(args.gt_out_dim * 2, 1024),
            nn.ReLU(),
            nn.Dropout(0.4),
            nn.Linear(1024, 1024),
            nn.ReLU(),
            nn.Dropout(0.4),
            nn.Linear(1024, 256),
            nn.ReLU(),
            nn.Dropout(0.4),
            nn.Linear(256, 2)
        )

    def forward(self, drdr_graph, didi_graph, drdipr_graph, drug_feature, disease_feature, protein_feature, sample):
        # Similarity branch: GT on similarity graphs
        dr_sim = self.gt_drug(drdr_graph)
        di_sim = self.gt_disease(didi_graph)

        # Topology branch: HGT on heterogeneous graph
        drug_feature = self.drug_linear(drug_feature)
        protein_feature = self.protein_linear(protein_feature)
        # disease_feature already has dim = hgt_in_dim (64)

        feature_dict = {
            'drug': drug_feature,
            'disease': disease_feature,
            'protein': protein_feature
        }

        drdipr_graph.ndata['h'] = feature_dict
        g = dgl.to_homogeneous(drdipr_graph, ndata='h')
        feature = torch.cat((drug_feature, disease_feature, protein_feature), dim=0)

        for layer in self.hgt:
            hgt_out = layer(g, feature, g.ndata['_TYPE'], g.edata['_TYPE'], presorted=True)
            feature = hgt_out

        # After hgt_dgl_last: output dim = hgt_head_dim * hgt_head = 25*8 = 200 = gt_out_dim
        dr_hgt = hgt_out[:self.args.drug_number, :]
        di_hgt = hgt_out[self.args.drug_number:self.args.disease_number + self.args.drug_number, :]

        # Stack similarity + topology embeddings (both dim = gt_out_dim = 200)
        dr = torch.stack((dr_sim, dr_hgt), dim=1)
        di = torch.stack((di_sim, di_hgt), dim=1)

        # Fuse with TransformerEncoder
        dr = self.drug_trans(dr)
        di = self.disease_trans(di)

        # Reshape: [num_nodes, 2, gt_out_dim] -> [num_nodes, 2*gt_out_dim]
        dr = dr.reshape(self.args.drug_number, 2 * self.args.gt_out_dim)
        di = di.reshape(self.args.disease_number, 2 * self.args.gt_out_dim)

        # Interaction: element-wise multiplication
        drdi_embedding = torch.mul(dr[sample[:, 0]], di[sample[:, 1]])
        output = self.mlp(drdi_embedding)

        return dr, output
