import numpy as np
import random
import torch
import pandas as pd
import dgl
import networkx as nx
from sklearn.model_selection import StratifiedKFold

device = torch.device('cuda')


def get_adj(edges, size):
    edges_tensor = torch.LongTensor(edges).t()
    values = torch.ones(len(edges))
    adj = torch.sparse.LongTensor(edges_tensor, values, size).to_dense().long()
    return adj


def k_matrix(matrix, k):
    num = matrix.shape[0]
    knn_graph = np.zeros(matrix.shape)

    idx_sort = np.argsort(-(matrix - np.eye(num)), axis=1)

    for i in range(num):
        knn_graph[i, idx_sort[i, :k + 1]] = matrix[i, idx_sort[i, :k + 1]]
        knn_graph[idx_sort[i, :k + 1], i] = matrix[idx_sort[i, :k + 1], i]

    return knn_graph + np.eye(num)


def normalize_degree(x):
    x = x.astype(np.float32)
    max_val = np.max(x)

    if max_val <= 0:
        return x.reshape(-1, 1)

    return (x / max_val).reshape(-1, 1)


def add_degree_topology_feature(data, args):
    """
    Topology feature nhẹ:
    - drug_degree
    - disease_degree
    - protein_degree

    Không dùng PageRank / betweenness / clustering.
    """

    drug_degree = np.zeros(args.drug_number, dtype=np.float32)
    disease_degree = np.zeros(args.disease_number, dtype=np.float32)
    protein_degree = np.zeros(args.protein_number, dtype=np.float32)

    drdi = np.asarray(data['drdi']).astype(int)
    drpr = np.asarray(data['drpr']).astype(int)
    dipr = np.asarray(data['dipr']).astype(int)

    # Drug-Disease
    for i in range(drdi.shape[0]):
        drug_id = drdi[i, 0]
        disease_id = drdi[i, 1]

        if 0 <= drug_id < args.drug_number:
            drug_degree[drug_id] += 1

        if 0 <= disease_id < args.disease_number:
            disease_degree[disease_id] += 1

    # Drug-Protein
    for i in range(drpr.shape[0]):
        drug_id = drpr[i, 0]
        protein_id = drpr[i, 1]

        if 0 <= drug_id < args.drug_number:
            drug_degree[drug_id] += 1

        if 0 <= protein_id < args.protein_number:
            protein_degree[protein_id] += 1

    # Disease-Protein
    # Dataset của bạn đang theo dạng: disease, protein
    for i in range(dipr.shape[0]):
        disease_id = dipr[i, 0]
        protein_id = dipr[i, 1]

        if 0 <= disease_id < args.disease_number:
            disease_degree[disease_id] += 1

        if 0 <= protein_id < args.protein_number:
            protein_degree[protein_id] += 1

    drug_degree = normalize_degree(drug_degree)
    disease_degree = normalize_degree(disease_degree)
    protein_degree = normalize_degree(protein_degree)

    data['drugfeature'] = np.concatenate(
        (data['drugfeature'], drug_degree),
        axis=1
    )

    data['diseasefeature'] = np.concatenate(
        (data['diseasefeature'], disease_degree),
        axis=1
    )

    data['proteinfeature'] = np.concatenate(
        (data['proteinfeature'], protein_degree),
        axis=1
    )

    print("Added topology degree feature:")
    print("drugfeature shape   :", data['drugfeature'].shape)
    print("diseasefeature shape:", data['diseasefeature'].shape)
    print("proteinfeature shape:", data['proteinfeature'].shape)

    return data


def get_data(args):
    data = dict()

    drf = pd.read_csv(args.data_dir + 'DrugFingerprint.csv').iloc[:, 1:].to_numpy()
    drg = pd.read_csv(args.data_dir + 'DrugGIP.csv').iloc[:, 1:].to_numpy()

    dip = pd.read_csv(args.data_dir + 'DiseasePS.csv').iloc[:, 1:].to_numpy()
    dig = pd.read_csv(args.data_dir + 'DiseaseGIP.csv').iloc[:, 1:].to_numpy()

    data['drug_number'] = int(drf.shape[0])
    data['disease_number'] = int(dig.shape[0])

    data['drf'] = drf
    data['drg'] = drg
    data['dip'] = dip
    data['dig'] = dig

    data['drdi'] = pd.read_csv(
        args.data_dir + 'DrugDiseaseAssociationNumber.csv',
        dtype=int
    ).to_numpy()

    data['drpr'] = pd.read_csv(
        args.data_dir + 'DrugProteinAssociationNumber.csv',
        dtype=int
    ).to_numpy()

    data['dipr'] = pd.read_csv(
        args.data_dir + 'ProteinDiseaseAssociationNumber.csv',
        dtype=int
    ).to_numpy()

    data['drugfeature'] = pd.read_csv(
        args.data_dir + 'Drug_mol2vec.csv',
        header=None
    ).iloc[:, 1:].to_numpy()

    data['diseasefeature'] = pd.read_csv(
        args.data_dir + 'DiseaseFeature.csv',
        header=None
    ).iloc[:, 1:].to_numpy()

    data['proteinfeature'] = pd.read_csv(
        args.data_dir + 'Protein_ESM.csv',
        header=None
    ).iloc[:, 1:].to_numpy()

    data['protein_number'] = data['proteinfeature'].shape[0]

    return data


def data_processing(data, args):
    drdi_matrix = get_adj(
        data['drdi'],
        (args.drug_number, args.disease_number)
    )

    one_index = []
    zero_index = []

    for i in range(drdi_matrix.shape[0]):
        for j in range(drdi_matrix.shape[1]):
            if drdi_matrix[i][j] >= 1:
                one_index.append([i, j])
            else:
                zero_index.append([i, j])

    random.seed(args.random_seed)
    random.shuffle(one_index)
    random.shuffle(zero_index)

    unsamples = zero_index[int(args.negative_rate * len(one_index)):]
    data['unsample'] = np.array(unsamples)

    zero_index = zero_index[:int(args.negative_rate * len(one_index))]

    index = np.array(one_index + zero_index, dtype=int)

    label = np.array(
        [1] * len(one_index) + [0] * len(zero_index),
        dtype=int
    )

    samples = np.concatenate(
        (index, np.expand_dims(label, axis=1)),
        axis=1
    )

    label_p = np.array([1] * len(one_index), dtype=int)

    drdi_p = samples[samples[:, 2] == 1, :]
    drdi_n = samples[samples[:, 2] == 0, :]

    drs_mean = (data['drf'] + data['drg']) / 2
    dis_mean = (data['dip'] + data['dig']) / 2

    drs = np.where(data['drf'] == 0, data['drg'], drs_mean)

    # Giữ logic gốc GitHub.
    dis = np.where(data['dip'] == 0, data['dip'], dis_mean)

    data['drs'] = drs
    data['dis'] = dis
    data['all_samples'] = samples
    data['all_drdi'] = samples[:, :2]
    data['all_drdi_p'] = drdi_p
    data['all_drdi_n'] = drdi_n
    data['all_label'] = label
    data['all_label_p'] = label_p

    # Thêm topology feature degree nhẹ
    data = add_degree_topology_feature(data, args)

    return data


def k_fold(data, args):
    k = args.k_fold

    skf = StratifiedKFold(
        n_splits=k,
        random_state=None,
        shuffle=False
    )

    X = data['all_drdi']
    Y = data['all_label']

    X_train_all = []
    X_test_all = []
    Y_train_all = []
    Y_test_all = []

    for train_index, test_index in skf.split(X, Y):
        X_train, X_test = X[train_index], X[test_index]
        Y_train, Y_test = Y[train_index], Y[test_index]

        Y_train = np.expand_dims(Y_train, axis=1).astype('float64')
        Y_test = np.expand_dims(Y_test, axis=1).astype('float64')

        X_train_all.append(X_train)
        X_test_all.append(X_test)
        Y_train_all.append(Y_train)
        Y_test_all.append(Y_test)

    for i in range(k):
        X_train1 = pd.DataFrame(
            data=np.concatenate((X_train_all[i], Y_train_all[i]), axis=1),
            columns=['drug', 'disease', 'label']
        )

        X_train1.to_csv(
            args.data_dir + 'fold/' + str(i) + '/data_train.csv'
        )

        X_test1 = pd.DataFrame(
            data=np.concatenate((X_test_all[i], Y_test_all[i]), axis=1),
            columns=['drug', 'disease', 'label']
        )

        X_test1.to_csv(
            args.data_dir + 'fold/' + str(i) + '/data_test.csv'
        )

    data['X_train'] = X_train_all
    data['X_test'] = X_test_all
    data['Y_train'] = Y_train_all
    data['Y_test'] = Y_test_all

    return data


def dgl_similarity_graph(data, args):
    drdr_matrix = k_matrix(data['drs'], args.neighbor)
    didi_matrix = k_matrix(data['dis'], args.neighbor)

    drdr_nx = nx.from_numpy_matrix(drdr_matrix)
    didi_nx = nx.from_numpy_matrix(didi_matrix)

    drdr_graph = dgl.from_networkx(drdr_nx)
    didi_graph = dgl.from_networkx(didi_nx)

    drdr_graph.ndata['drs'] = torch.tensor(data['drs'])
    didi_graph.ndata['dis'] = torch.tensor(data['dis'])

    return drdr_graph, didi_graph, data


def dgl_heterograph(data, drdi, args):
    """
    AMDGT + 6-edge + topology degree feature.

    6 edge type:
    1. drug -> disease
    2. disease -> drug
    3. drug -> protein
    4. protein -> drug
    5. disease -> protein
    6. protein -> disease
    """

    drdi = np.asarray(drdi).astype(int)
    drpr = np.asarray(data['drpr']).astype(int)
    dipr = np.asarray(data['dipr']).astype(int)

    drdi = drdi[:, :2]

    # Drug <-> Disease
    drdi_src = torch.LongTensor(drdi[:, 0])
    drdi_dst = torch.LongTensor(drdi[:, 1])

    # Drug <-> Protein
    drpr_src = torch.LongTensor(drpr[:, 0])
    drpr_dst = torch.LongTensor(drpr[:, 1])

    # Disease <-> Protein
    # Dataset của bạn đang theo dạng: disease, protein
    dipr_src = torch.LongTensor(dipr[:, 0])
    dipr_dst = torch.LongTensor(dipr[:, 1])

    node_dict = {
        'drug': args.drug_number,
        'disease': args.disease_number,
        'protein': args.protein_number
    }

    heterograph_dict = {
        # Drug <-> Disease
        ('drug', 'drug_disease', 'disease'): (drdi_src, drdi_dst),
        ('disease', 'disease_drug', 'drug'): (drdi_dst, drdi_src),

        # Drug <-> Protein
        ('drug', 'drug_protein', 'protein'): (drpr_src, drpr_dst),
        ('protein', 'protein_drug', 'drug'): (drpr_dst, drpr_src),

        # Disease <-> Protein
        ('disease', 'disease_protein', 'protein'): (dipr_src, dipr_dst),
        ('protein', 'protein_disease', 'disease'): (dipr_dst, dipr_src),
    }

    data['feature_dict'] = {
        'drug': torch.tensor(data['drugfeature']),
        'disease': torch.tensor(data['diseasefeature']),
        'protein': torch.tensor(data['proteinfeature'])
    }

    drdipr_graph = dgl.heterograph(
        heterograph_dict,
        num_nodes_dict=node_dict
    )

    return drdipr_graph, data