import dgl
import dgl.nn.pytorch
import torch
import torch.nn as nn

from model import gt_net_drug, gt_net_disease

device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')


class AMNTDDA(nn.Module):
    def __init__(self, args):
        super(AMNTDDA, self).__init__()

        self.args = args

        topology_dim = getattr(args, 'topology_feature_dim', 8)
        drug_feature_dim = getattr(args, 'drug_feature_dim', 300 + topology_dim)
        disease_feature_dim = getattr(args, 'disease_feature_dim', 64 + topology_dim)
        protein_feature_dim = getattr(args, 'protein_feature_dim', 320 + topology_dim)

        self.topology_dim = topology_dim

        self.drug_linear = nn.Linear(drug_feature_dim, args.hgt_in_dim)
        self.disease_linear = nn.Linear(disease_feature_dim, args.hgt_in_dim)
        self.protein_linear = nn.Linear(protein_feature_dim, args.hgt_in_dim)

        self.drug_topology_linear = nn.Sequential(
            nn.Linear(topology_dim, args.gt_out_dim),
            nn.ReLU(),
            nn.Dropout(args.dropout),
            nn.Linear(args.gt_out_dim, args.gt_out_dim)
        )

        self.disease_topology_linear = nn.Sequential(
            nn.Linear(topology_dim, args.gt_out_dim),
            nn.ReLU(),
            nn.Dropout(args.dropout),
            nn.Linear(args.gt_out_dim, args.gt_out_dim)
        )

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

        num_node_types = 3
        num_edge_types = 9

        self.hgt_dgl = dgl.nn.pytorch.conv.HGTConv(
            args.hgt_in_dim,
            int(args.hgt_in_dim / args.hgt_head),
            args.hgt_head,
            num_node_types,
            num_edge_types,
            args.dropout
        )

        self.hgt_dgl_last = dgl.nn.pytorch.conv.HGTConv(
            args.hgt_in_dim,
            args.hgt_head_dim,
            args.hgt_head,
            num_node_types,
            num_edge_types,
            args.dropout
        )

        self.hgt = nn.ModuleList()

        for _ in range(args.hgt_layer - 1):
            self.hgt.append(self.hgt_dgl)

        self.hgt.append(self.hgt_dgl_last)

        # Normalize từng nhánh
        self.drug_sim_norm = nn.LayerNorm(args.gt_out_dim)
        self.disease_sim_norm = nn.LayerNorm(args.gt_out_dim)

        self.drug_hgt_norm = nn.LayerNorm(args.gt_out_dim)
        self.disease_hgt_norm = nn.LayerNorm(args.gt_out_dim)

        self.drug_topology_norm = nn.LayerNorm(args.gt_out_dim)
        self.disease_topology_norm = nn.LayerNorm(args.gt_out_dim)

        self.drug_fusion_norm = nn.LayerNorm(args.gt_out_dim)
        self.disease_fusion_norm = nn.LayerNorm(args.gt_out_dim)

        # Gate học được để điều chỉnh ảnh hưởng topology
        self.drug_topology_gate = nn.Sequential(
            nn.Linear(args.gt_out_dim * 3, args.gt_out_dim),
            nn.Sigmoid()
        )

        self.disease_topology_gate = nn.Sequential(
            nn.Linear(args.gt_out_dim * 3, args.gt_out_dim),
            nn.Sigmoid()
        )

        encoder_layer = nn.TransformerEncoderLayer(
            d_model=args.gt_out_dim,
            nhead=args.tr_head,
            batch_first=True
        )

        self.drug_trans = nn.TransformerEncoder(
            encoder_layer,
            num_layers=args.tr_layer
        )

        self.disease_trans = nn.TransformerEncoder(
            encoder_layer,
            num_layers=args.tr_layer
        )

        self.final_drug_norm = nn.LayerNorm(args.gt_out_dim * 2)
        self.final_disease_norm = nn.LayerNorm(args.gt_out_dim * 2)

        # Interaction fusion:
        # [drug, disease, drug*disease, abs(drug-disease)]
        # = 4 * (2 * gt_out_dim)
        interaction_dim = args.gt_out_dim * 8

        self.mlp = nn.Sequential(
            nn.Linear(interaction_dim, 1024),
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

    def forward(
        self,
        drdr_graph,
        didi_graph,
        drdipr_graph,
        drug_feature,
        disease_feature,
        protein_feature,
        sample
    ):
        # Similarity graph branch
        dr_sim = self.gt_drug(drdr_graph)
        di_sim = self.gt_disease(didi_graph)

        dr_sim = self.drug_sim_norm(dr_sim)
        di_sim = self.disease_sim_norm(di_sim)

        # Heterogeneous topology branch
        drug_topology_raw = drug_feature[:, -self.topology_dim:]
        disease_topology_raw = disease_feature[:, -self.topology_dim:]

        drug_feature = self.drug_linear(drug_feature)
        disease_feature = self.disease_linear(disease_feature)
        protein_feature = self.protein_linear(protein_feature)

        feature_dict = {
            'drug': drug_feature,
            'disease': disease_feature,
            'protein': protein_feature
        }

        drdipr_graph.ndata['h'] = feature_dict

        g = dgl.to_homogeneous(drdipr_graph, ndata='h')

        hgt_out = torch.cat(
            (
                drug_feature,
                disease_feature,
                protein_feature
            ),
            dim=0
        )

        for layer in self.hgt:
            hgt_out = layer(
                g,
                hgt_out,
                g.ndata['_TYPE'],
                g.edata['_TYPE'],
                presorted=True
            )

        dr_hgt = hgt_out[:self.args.drug_number, :]

        di_hgt = hgt_out[
            self.args.drug_number:
            self.args.drug_number + self.args.disease_number,
            :
        ]

        dr_hgt = self.drug_hgt_norm(dr_hgt)
        di_hgt = self.disease_hgt_norm(di_hgt)

        dr_topology = self.drug_topology_linear(drug_topology_raw)
        di_topology = self.disease_topology_linear(disease_topology_raw)

        dr_topology = self.drug_topology_norm(dr_topology)
        di_topology = self.disease_topology_norm(di_topology)

        dr_hgt = self.drug_fusion_norm(dr_hgt + dr_topology)
        di_hgt = self.disease_fusion_norm(di_hgt + di_topology)

        # Per-dimension gate learns how much topology to mix with similarity.
        drug_gate = self.drug_topology_gate(
            torch.cat((dr_sim, dr_hgt, dr_topology), dim=1)
        )
        disease_gate = self.disease_topology_gate(
            torch.cat((di_sim, di_hgt, di_topology), dim=1)
        )

        dr_hgt = self.drug_fusion_norm(drug_gate * dr_hgt + (1.0 - drug_gate) * dr_sim)
        di_hgt = self.disease_fusion_norm(disease_gate * di_hgt + (1.0 - disease_gate) * di_sim)

        # Stack 2 nhánh
        dr = torch.stack((dr_sim, dr_hgt), dim=1)
        di = torch.stack((di_sim, di_hgt), dim=1)

        dr = self.drug_trans(dr)
        di = self.disease_trans(di)

        dr = dr.view(self.args.drug_number, 2 * self.args.gt_out_dim)
        di = di.view(self.args.disease_number, 2 * self.args.gt_out_dim)

        dr = self.final_drug_norm(dr)
        di = self.final_disease_norm(di)

        drug_emb = dr[sample[:, 0]]
        disease_emb = di[sample[:, 1]]

        # Interaction fusion nâng cấp
        mul_emb = torch.mul(drug_emb, disease_emb)
        diff_emb = torch.abs(drug_emb - disease_emb)

        drdi_embedding = torch.cat(
            (
                drug_emb,
                disease_emb,
                mul_emb,
                diff_emb
            ),
            dim=1
        )

        output = self.mlp(drdi_embedding)

        return dr, output
