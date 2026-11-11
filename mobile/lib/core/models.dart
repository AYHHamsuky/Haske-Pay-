int _i(dynamic v) => v is int ? v : int.tryParse('$v') ?? 0;
DateTime? _d(dynamic v) => v == null ? null : DateTime.tryParse('$v');

class VirtualAccount {
  VirtualAccount.fromJson(Map j)
      : bank = j['bank'],
        number = j['account_number'],
        name = j['account_name'];
  final String bank, number, name;
}

class Me {
  Me.fromJson(Map j)
      : id = _i(j['id']),
        name = j['name'],
        phone = j['phone'],
        role = j['role'],
        locale = j['locale'] ?? 'en',
        hasPin = j['has_pin'] == true,
        balance = _i(j['balance']),
        dailyLimit = _i(j['daily_limit']),
        spentToday = _i(j['spent_today']),
        support = j['support_whatsapp'] ?? '',
        agentBusiness = (j['agent'] as Map?)?['business_name'],
        agentTier = (j['agent'] as Map?)?['tier'],
        va = j['virtual_account'] == null ? null : VirtualAccount.fromJson(j['virtual_account']);

  final int id, balance, dailyLimit, spentToday;
  final String? name, agentBusiness, agentTier;
  final String phone, role, locale, support;
  final bool hasPin;
  final VirtualAccount? va;

  bool get isAgent => role == 'agent';
}

class Service {
  Service.fromJson(Map j)
      : code = j['code'],
        name = j['name'],
        shortName = j['short_name'],
        category = j['category'],
        network = j['network'],
        active = j['active'] == true,
        fee = _i(j['fee']),
        min = _i(j['min_amount']),
        max = _i(j['max_amount']);
  final String code, name, shortName, category;
  final String? network;
  final bool active;
  final int fee, min, max;
}

class Plan {
  Plan.fromJson(Map j)
      : code = j['code'],
        name = j['name'],
        amount = _i(j['amount']);
  final String code, name;
  final int amount;
}

class SavedMeter {
  SavedMeter.fromJson(Map j)
      : id = _i(j['id']),
        serviceCode = j['service_code'],
        meter = j['meter_number'],
        type = j['meter_type'],
        name = j['customer_name'];
  final int id;
  final String serviceCode, meter, type, name;
}

class Customer {
  Customer.fromJson(Map j)
      : name = j['name'],
        address = j['address'],
        band = j['band'];
  final String name;
  final String? address, band;
}

/// A purchase or a wallet top-up in the history list.
class Tx {
  Tx.fromJson(Map j)
      : kind = j['kind'] ?? 'purchase',
        reference = j['reference'],
        category = j['category'],
        serviceName = j['service_name'],
        shortName = j['short_name'],
        target = j['target'] ?? '',
        meterType = j['meter_type'],
        plan = j['plan'],
        customerName = j['customer_name'],
        amount = _i(j['amount']),
        fee = _i(j['fee']),
        total = _i(j['total']),
        agentCommission = _i(j['agent_commission']),
        status = j['status'],
        provider = j['provider'],
        token = j['token'],
        units = j['units'],
        failureReason = j['failure_reason'],
        requeryCount = _i(j['requery_count']),
        createdAt = _d(j['created_at']);

  final String kind, reference, target, status;
  final String? category, serviceName, shortName, meterType, plan, customerName, provider, token, units, failureReason;
  final int amount, fee, total, agentCommission, requeryCount;
  final DateTime? createdAt;

  bool get isFunding => kind == 'funding';
  bool get isFinal => status != 'pending';
}
