import 'package:flutter/material.dart';

import 'api_client.dart';

const _darkBackground = Color(0xFF101821);
const _darkSurface = Color(0xFF192530);
const _darkAccent = Color(0xFF72E0BE);
const _lightAccent = Color(0xFF087F63);
const _darkDanger = Color(0xFFFF877C);
const _lightDanger = Color(0xFFB3261E);
const _themeTransitionDuration = Duration(milliseconds: 320);
const _pageTransitionDuration = Duration(milliseconds: 240);
const _motionCurve = Curves.easeInOutCubic;

ThemeData _buildTheme(Brightness brightness) {
  final isDark = brightness == Brightness.dark;
  final accent = isDark ? _darkAccent : _lightAccent;
  final danger = isDark ? _darkDanger : _lightDanger;
  final background = isDark ? _darkBackground : const Color(0xFFF4F7F8);
  final surface = isDark ? _darkSurface : Colors.white;
  final colorScheme =
      ColorScheme.fromSeed(seedColor: accent, brightness: brightness).copyWith(
        primary: accent,
        onPrimary: isDark ? _darkBackground : Colors.white,
        error: danger,
        onError: Colors.white,
        surface: surface,
      );

  return ThemeData(
    brightness: brightness,
    scaffoldBackgroundColor: background,
    colorScheme: colorScheme,
    appBarTheme: AppBarTheme(
      backgroundColor: background,
      foregroundColor: colorScheme.onSurface,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
        color: colorScheme.onSurface,
        fontSize: 21,
        fontWeight: FontWeight.w800,
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: isDark ? surface : const Color(0xFFEBF0F2),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide.none,
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: colorScheme.outlineVariant),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: accent, width: 1.5),
      ),
    ),
    cardTheme: CardThemeData(
      color: surface,
      margin: EdgeInsets.zero,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(
          color: colorScheme.outlineVariant.withValues(alpha: 0.45),
        ),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: surface,
      indicatorColor: accent.withValues(alpha: isDark ? 0.2 : 0.13),
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return TextStyle(
          fontSize: 12,
          fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
        );
      }),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    ),
    useMaterial3: true,
  );
}

void main() {
  runApp(const MyApp());
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  Map<String, dynamic>? _user;
  bool _loading = true;
  ThemeMode _themeMode = ThemeMode.dark;

  @override
  void initState() {
    super.initState();
    ApiClient.onUnauthorized = _handleUnauthorized;
    _restoreSession();
  }

  void _handleUnauthorized() {
    if (mounted) setState(() => _user = null);
  }

  Future<void> _restoreSession() async {
    final savedTheme = await ApiClient.readThemeMode();
    if (savedTheme == 'light') _themeMode = ThemeMode.light;
    final token = await ApiClient.readToken();
    if (token != null) {
      try {
        final response = await ApiClient.get('auth.php') as Map;
        _user = Map<String, dynamic>.from(response['user'] as Map);
      } on ApiException {
        // Preserve the token when the server is temporarily unreachable.
      }
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _toggleTheme() async {
    final nextMode = _themeMode == ThemeMode.dark
        ? ThemeMode.light
        : ThemeMode.dark;
    await ApiClient.saveThemeMode(nextMode.name);
    if (mounted) setState(() => _themeMode = nextMode);
  }

  void _signedIn(Map<String, dynamic> user) {
    setState(() => _user = user);
  }

  Future<void> _signOut() async {
    try {
      await ApiClient.post('auth.php', {'action': 'logout'});
    } on ApiException {
      // A cleared or expired server session still signs the user out locally.
    }
    await ApiClient.clearToken();
    if (mounted) setState(() => _user = null);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'StudySys',
      debugShowCheckedModeBanner: false,
      theme: _buildTheme(Brightness.light),
      darkTheme: _buildTheme(Brightness.dark),
      themeMode: _themeMode,
      themeAnimationDuration: _themeTransitionDuration,
      themeAnimationCurve: _motionCurve,
      home: _loading
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _user == null
          ? LoginScreen(
              onSignedIn: _signedIn,
              isDarkMode: _themeMode == ThemeMode.dark,
              onToggleTheme: _toggleTheme,
            )
          : HomeScreen(
              user: _user!,
              onSignOut: _signOut,
              isDarkMode: _themeMode == ThemeMode.dark,
              onToggleTheme: _toggleTheme,
            ),
    );
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    required this.onSignedIn,
    required this.isDarkMode,
    required this.onToggleTheme,
    super.key,
  });

  final ValueChanged<Map<String, dynamic>> onSignedIn;
  final bool isDarkMode;
  final Future<void> Function() onToggleTheme;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _registering = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await ApiClient.post('auth.php', {
        'action': _registering ? 'register' : 'login',
        if (_registering) 'name': _name.text.trim(),
        'email': _email.text.trim(),
        'password': _password.text,
      }) as Map;
      await ApiClient.saveToken(result['token'] as String);
      widget.onSignedIn(Map<String, dynamic>.from(result['user'] as Map));
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Align(
                      alignment: Alignment.centerRight,
                      child: IconButton(
                        tooltip: widget.isDarkMode
                            ? 'Switch to light mode'
                            : 'Switch to dark mode',
                        onPressed: widget.onToggleTheme,
                        icon: AnimatedSwitcher(
                          duration: const Duration(milliseconds: 220),
                          switchInCurve: Curves.easeOutBack,
                          switchOutCurve: Curves.easeIn,
                          transitionBuilder: (child, animation) =>
                              RotationTransition(
                                turns: Tween<double>(
                                  begin: -0.08,
                                  end: 0,
                                ).animate(animation),
                                child: FadeTransition(
                                  opacity: animation,
                                  child: child,
                                ),
                              ),
                          child: Icon(
                            widget.isDarkMode
                                ? Icons.light_mode_outlined
                                : Icons.dark_mode_outlined,
                            key: ValueKey(widget.isDarkMode),
                          ),
                        ),
                      ),
                    ),
                    Icon(Icons.auto_awesome, color: colors.primary, size: 48),
                    const SizedBox(height: 12),
                    const Text(
                      'StudySys',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 32,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _registering
                          ? 'Create your student account'
                          : 'Your studies, all in one place',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: colors.onSurfaceVariant),
                    ),
                    const SizedBox(height: 32),
                    if (_registering) ...[
                      TextFormField(
                        controller: _name,
                        textCapitalization: TextCapitalization.words,
                        decoration: const InputDecoration(labelText: 'Name'),
                        validator: (value) =>
                            value == null || value.trim().isEmpty
                            ? 'Enter your name'
                            : null,
                      ),
                      const SizedBox(height: 14),
                    ],
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(labelText: 'Email'),
                      validator: (value) =>
                          value == null ||
                              !RegExp(r'^[^@]+@[^@]+\.[^@]+$').hasMatch(value)
                          ? 'Enter a valid email'
                          : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _password,
                      obscureText: true,
                      decoration: const InputDecoration(labelText: 'Password'),
                      validator: (value) => value == null || value.length < 6
                          ? 'Use at least 6 characters'
                          : null,
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 14),
                      Text(_error!, style: TextStyle(color: colors.error)),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      style: FilledButton.styleFrom(
                        backgroundColor: colors.primary,
                        foregroundColor: colors.onPrimary,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                      ),
                      child: _busy
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(_registering ? 'Create account' : 'Sign in'),
                    ),
                    TextButton(
                      onPressed: _busy
                          ? null
                          : () => setState(() {
                              _registering = !_registering;
                              _error = null;
                            }),
                      child: Text(
                        _registering
                            ? 'Already have an account? Sign in'
                            : 'New here? Create an account',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    required this.user,
    required this.onSignOut,
    required this.isDarkMode,
    required this.onToggleTheme,
    super.key,
  });

  final Map<String, dynamic> user;
  final Future<void> Function() onSignOut;
  final bool isDarkMode;
  final Future<void> Function() onToggleTheme;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selected = 0;

  @override
  Widget build(BuildContext context) {
    final pages = [
      DashboardPage(user: widget.user),
      const BudgetPage(),
      const SchedulePage(),
      const CalendarPage(),
    ];
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'StudySys',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        actions: [
          IconButton(
            tooltip: widget.isDarkMode
                ? 'Switch to light mode'
                : 'Switch to dark mode',
            onPressed: widget.onToggleTheme,
            icon: AnimatedSwitcher(
              duration: const Duration(milliseconds: 220),
              switchInCurve: Curves.easeOutBack,
              switchOutCurve: Curves.easeIn,
              transitionBuilder: (child, animation) => RotationTransition(
                turns: Tween<double>(begin: -0.08, end: 0).animate(animation),
                child: FadeTransition(opacity: animation, child: child),
              ),
              child: Icon(
                widget.isDarkMode
                    ? Icons.light_mode_outlined
                    : Icons.dark_mode_outlined,
                key: ValueKey(widget.isDarkMode),
              ),
            ),
          ),
          IconButton(
            tooltip: 'Sign out',
            onPressed: widget.onSignOut,
            icon: const Icon(Icons.logout_rounded),
          ),
        ],
      ),
      body: AnimatedPageStack(index: _selected, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selected,
        onDestinationSelected: (value) => setState(() => _selected = value),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.space_dashboard_outlined),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.account_balance_wallet_outlined),
            label: 'Budget',
          ),
          NavigationDestination(
            icon: Icon(Icons.menu_book_outlined),
            label: 'Schedule',
          ),
          NavigationDestination(
            icon: Icon(Icons.calendar_month_outlined),
            label: 'Calendar',
          ),
        ],
      ),
    );
  }
}

class AnimatedPageStack extends StatelessWidget {
  const AnimatedPageStack({
    required this.index,
    required this.children,
    super.key,
  });

  final int index;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Stack(
    fit: StackFit.expand,
    children: [
      for (var pageIndex = 0; pageIndex < children.length; pageIndex++)
        IgnorePointer(
          ignoring: pageIndex != index,
          child: ExcludeSemantics(
            excluding: pageIndex != index,
            child: AnimatedSlide(
              offset: pageIndex == index ? Offset.zero : const Offset(0, 0.025),
              duration: _pageTransitionDuration,
              curve: _motionCurve,
              child: AnimatedOpacity(
                opacity: pageIndex == index ? 1 : 0,
                duration: _pageTransitionDuration,
                curve: Curves.easeOut,
                child: children[pageIndex],
              ),
            ),
          ),
        ),
    ],
  );
}

class DashboardPage extends StatefulWidget {
  const DashboardPage({required this.user, super.key});

  final Map<String, dynamic> user;

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  late Future<Map<String, dynamic>> _data;

  @override
  void initState() {
    super.initState();
    _data = _load();
  }

  Future<Map<String, dynamic>> _load() async =>
      Map<String, dynamic>.from(await ApiClient.get('dashboard.php') as Map);

  Future<void> _refresh() async {
    if (mounted) setState(() => _data = _load());
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<Map<String, dynamic>>(
        future: _data,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return ErrorState(error: snapshot.error, retry: _refresh);
          }
          if (!snapshot.hasData) return const LoadingState();
          final data = snapshot.data!;
          final income = number(data['income']);
          final expense = number(data['expense']);
          final classes = List<Map<String, dynamic>>.from(
            data['today_schedule'] as List,
          );
          final events = List<Map<String, dynamic>>.from(
            data['upcoming_events'] as List,
          );
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
            children: [
              Text(
                'Hello, ${widget.user['name'] ?? 'student'}',
                style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              Text(
                dateLabel(DateTime.now()),
                style: TextStyle(color: colors.onSurfaceVariant),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: SummaryCard(
                      label: 'Income',
                      value: money(income),
                      color: colors.primary,
                      icon: Icons.south_west_rounded,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: SummaryCard(
                      label: 'Expenses',
                      value: money(expense),
                      color: colors.error,
                      icon: Icons.north_east_rounded,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              BalanceCard(balance: income - expense),
              const SizedBox(height: 24),
              SectionTitle(
                title: 'Today’s classes',
                trailing: '${classes.length}',
              ),
              const SizedBox(height: 10),
              if (classes.isEmpty)
                const EmptyCard(message: 'No classes on your schedule today.')
              else
                ...classes.map(
                  (item) => InfoCard(
                    icon: Icons.menu_book_rounded,
                    title: item['subject'] as String,
                    subtitle:
                        '${timeLabel(item['start_time'])} – ${timeLabel(item['end_time'])}${item['room'] == null || item['room'] == '' ? '' : ' · ${item['room']}'}',
                  ),
                ),
              const SizedBox(height: 24),
              const SectionTitle(title: 'Upcoming events'),
              const SizedBox(height: 10),
              if (events.isEmpty)
                const EmptyCard(
                  message: 'Nothing coming up. Enjoy the breathing room.',
                )
              else
                ...events.map(
                  (item) => InfoCard(
                    icon: Icons.event_rounded,
                    title: item['title'] as String,
                    subtitle:
                        '${dateLabel(DateTime.parse(item['event_date'] as String))}${item['event_time'] == null ? '' : ' · ${timeLabel(item['event_time'])}'}',
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class BudgetPage extends StatefulWidget {
  const BudgetPage({super.key});

  @override
  State<BudgetPage> createState() => _BudgetPageState();
}

class _BudgetPageState extends State<BudgetPage> {
  late Future<Map<String, dynamic>> _data;

  @override
  void initState() {
    super.initState();
    _data = _load();
  }

  Future<Map<String, dynamic>> _load() async =>
      Map<String, dynamic>.from(await ApiClient.get('budget.php') as Map);

  Future<void> _refresh() async {
    if (mounted) setState(() => _data = _load());
  }

  Future<void> _add(Map<String, dynamic> data) async {
    final categories = List<Map<String, dynamic>>.from(
      data['categories'] as List,
    );
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => TransactionDialog(categories: categories),
    );
    if (saved == true && mounted) await _refresh();
  }

  Future<void> _editTransaction(
    Map<String, dynamic> data,
    Map<String, dynamic> transaction,
  ) async {
    final categories = List<Map<String, dynamic>>.from(
      data['categories'] as List,
    );
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) =>
          TransactionDialog(categories: categories, transaction: transaction),
    );
    if (saved == true && mounted) await _refresh();
  }

  Future<void> _addCategory() async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => const CategoryDialog(),
    );
    if (saved == true && mounted) await _refresh();
  }

  Future<void> _delete(int id) async {
    try {
      await ApiClient.post('budget.php', {
        'action': 'delete_transaction',
        'id': id,
      });
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    }
  }

  Future<void> _deleteCategory(Map<String, dynamic> category) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete category?'),
        content: Text(
          'Delete ${category['name']}? Existing transactions will remain uncategorized.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await ApiClient.post('budget.php', {
        'action': 'delete_category',
        'id': int.parse(category['id'].toString()),
      });
      if (mounted) await _refresh();
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return FutureBuilder<Map<String, dynamic>>(
      future: _data,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return ErrorState(error: snapshot.error, retry: _refresh);
        }
        if (!snapshot.hasData) return const LoadingState();
        final data = snapshot.data!;
        final transactions = List<Map<String, dynamic>>.from(
          data['transactions'] as List,
        );
        final categories = List<Map<String, dynamic>>.from(
          data['categories'] as List,
        );
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            PageHeading(
              title: 'Budget',
              subtitle: 'This month and your recent activity',
              action: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    onPressed: _addCategory,
                    icon: const Icon(Icons.create_new_folder_outlined),
                    tooltip: 'Add category',
                  ),
                  IconButton.filled(
                    onPressed: () => _add(data),
                    icon: const Icon(Icons.add),
                    tooltip: 'Add transaction',
                  ),
                ],
              ),
            ),
            Row(
              children: [
                Expanded(
                  child: SummaryCard(
                    label: 'Income this month',
                    value: money(number(data['monthly_income'])),
                    color: colors.primary,
                    icon: Icons.south_west_rounded,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: SummaryCard(
                    label: 'Expenses this month',
                    value: money(number(data['monthly_expense'])),
                    color: colors.error,
                    icon: Icons.north_east_rounded,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
            const SectionTitle(title: 'Categories'),
            const SizedBox(height: 10),
            if (categories.isEmpty)
              const EmptyCard(
                message: 'Create a category to organize your spending.',
              )
            else
              ...categories.map((category) {
                final limit = number(category['monthly_limit']);
                final spent = number(category['spent']);
                return Dismissible(
                  key: ValueKey('category-${category['id']}'),
                  direction: DismissDirection.endToStart,
                  background: const DeleteBackground(),
                  confirmDismiss: (_) async {
                    await _deleteCategory(category);
                    return false;
                  },
                  child: InfoCard(
                    icon: Icons.sell_outlined,
                    title: category['name'] as String,
                    subtitle: limit > 0
                        ? '${money(spent)} spent of ${money(limit)} monthly limit'
                        : '${money(spent)} spent this month · no limit',
                    trailing: limit > 0
                        ? Text(
                            '${(spent / limit * 100).clamp(0, 999).toStringAsFixed(0)}%',
                            style: TextStyle(
                              color: spent > limit
                                  ? colors.error
                                  : colors.primary,
                            ),
                          )
                        : null,
                  ),
                );
              }),
            const SizedBox(height: 20),
            const SectionTitle(title: 'Transactions'),
            const SizedBox(height: 10),
            if (transactions.isEmpty)
              const EmptyCard(
                message: 'Add your first transaction to get started.',
              )
            else
              ...transactions.map((item) {
                final isIncome = item['type'] == 'income';
                return Dismissible(
                  key: ValueKey('transaction-${item['id']}'),
                  direction: DismissDirection.endToStart,
                  background: const DeleteBackground(),
                  onDismissed: (_) => _delete(int.parse(item['id'].toString())),
                  child: InfoCard(
                    icon: isIncome
                        ? Icons.south_west_rounded
                        : Icons.north_east_rounded,
                    iconColor: isIncome ? colors.primary : colors.error,
                    title:
                        item['note'] as String? ??
                        (item['category_name'] as String? ?? 'Transaction'),
                    subtitle:
                        '${item['category_name'] ?? 'Uncategorized'} · ${dateLabel(DateTime.parse(item['txn_date'] as String))}',
                    onTap: () => _editTransaction(data, item),
                    trailing: Text(
                      '${isIncome ? '+' : '−'}${money(number(item['amount']))}',
                      style: TextStyle(
                        color: isIncome ? colors.primary : colors.error,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                );
              }),
          ],
        );
      },
    );
  }
}

class CategoryDialog extends StatefulWidget {
  const CategoryDialog({super.key});

  @override
  State<CategoryDialog> createState() => _CategoryDialogState();
}

class _CategoryDialogState extends State<CategoryDialog> {
  final _name = TextEditingController();
  final _limit = TextEditingController(text: '0');
  bool _busy = false;

  @override
  void dispose() {
    _name.dispose();
    _limit.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final limit = double.tryParse(_limit.text);
    if (_name.text.trim().isEmpty || limit == null || limit < 0) {
      showMessage(
        context,
        'Enter a category name and a valid non-negative limit.',
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await ApiClient.post('budget.php', {
        'action': 'save_category',
        'name': _name.text.trim(),
        'monthly_limit': limit,
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Add category'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        TextField(
          controller: _name,
          decoration: const InputDecoration(labelText: 'Name'),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _limit,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(labelText: 'Monthly limit'),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: _busy ? null : () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(onPressed: _busy ? null : _save, child: const Text('Save')),
    ],
  );
}

class TransactionDialog extends StatefulWidget {
  const TransactionDialog({
    required this.categories,
    this.transaction,
    super.key,
  });

  final List<Map<String, dynamic>> categories;
  final Map<String, dynamic>? transaction;

  @override
  State<TransactionDialog> createState() => _TransactionDialogState();
}

class _TransactionDialogState extends State<TransactionDialog> {
  late final TextEditingController _amount;
  late final TextEditingController _note;
  late String _type;
  String? _category;
  late DateTime _date;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final transaction = widget.transaction;
    _amount = TextEditingController(
      text: transaction?['amount']?.toString() ?? '',
    );
    _note = TextEditingController(text: transaction?['note'] as String? ?? '');
    _type = transaction?['type'] as String? ?? 'expense';
    _category = transaction?['category_id']?.toString();
    _date = transaction == null
        ? DateTime.now()
        : DateTime.parse(transaction['txn_date'] as String);
  }

  @override
  void dispose() {
    _amount.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final amount = double.tryParse(_amount.text);
    if (amount == null || amount <= 0) {
      showMessage(context, 'Enter an amount greater than zero.');
      return;
    }
    setState(() => _busy = true);
    try {
      await ApiClient.post('budget.php', {
        'action': 'save_transaction',
        if (widget.transaction != null)
          'id': int.parse(widget.transaction!['id'].toString()),
        'amount': amount,
        'type': _type,
        'category_id': _category,
        'note': _note.text.trim(),
        'txn_date': dateKey(_date),
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        widget.transaction == null ? 'New transaction' : 'Edit transaction',
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _amount,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(labelText: 'Amount'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: const InputDecoration(labelText: 'Type'),
              items: const [
                DropdownMenuItem(value: 'expense', child: Text('Expense')),
                DropdownMenuItem(value: 'income', child: Text('Income')),
              ],
              onChanged: (value) => setState(() => _type = value ?? 'expense'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _category,
              decoration: const InputDecoration(labelText: 'Category'),
              items: [
                const DropdownMenuItem(
                  value: null,
                  child: Text('Uncategorized'),
                ),
                ...widget.categories.map(
                  (category) => DropdownMenuItem(
                    value: category['id'].toString(),
                    child: Text(category['name'] as String),
                  ),
                ),
              ],
              onChanged: (value) => setState(() => _category = value),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _note,
              decoration: const InputDecoration(labelText: 'Note'),
            ),
            TextButton.icon(
              onPressed: () async {
                final selected = await showDatePicker(
                  context: context,
                  initialDate: _date,
                  firstDate: DateTime(2000),
                  lastDate: DateTime(2100),
                );
                if (selected != null) setState(() => _date = selected);
              },
              icon: const Icon(Icons.calendar_today),
              label: Text(dateLabel(_date)),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _busy ? null : _save,
          child: const Text('Save'),
        ),
      ],
    );
  }
}

class SchedulePage extends StatefulWidget {
  const SchedulePage({super.key});

  @override
  State<SchedulePage> createState() => _SchedulePageState();
}

class _SchedulePageState extends State<SchedulePage> {
  late Future<List<Map<String, dynamic>>> _data;
  static const _dayNames = {
    'MON': 'Monday',
    'TUE': 'Tuesday',
    'WED': 'Wednesday',
    'THU': 'Thursday',
    'FRI': 'Friday',
    'SAT': 'Saturday',
    'SUN': 'Sunday',
  };

  @override
  void initState() {
    super.initState();
    _data = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async =>
      List<Map<String, dynamic>>.from(
        await ApiClient.get('schedule.php') as List,
      );

  Future<void> _refresh() async {
    if (mounted) setState(() => _data = _load());
  }

  Future<void> _edit([Map<String, dynamic>? item]) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => ScheduleDialog(item: item),
    );
    if (saved == true && mounted) await _refresh();
  }

  Future<void> _delete(int id) async {
    try {
      await ApiClient.post('schedule.php', {'action': 'delete', 'id': id});
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _data,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return ErrorState(error: snapshot.error, retry: _refresh);
        }
        if (!snapshot.hasData) return const LoadingState();
        final items = snapshot.data!;
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            PageHeading(
              title: 'Schedule',
              subtitle: 'Your weekly classes',
              action: IconButton.filled(
                onPressed: _edit,
                icon: const Icon(Icons.add),
                tooltip: 'Add class',
              ),
            ),
            if (items.isEmpty)
              const EmptyCard(
                message: 'Add classes to see your weekly schedule here.',
              )
            else
              for (final entry in _dayNames.entries) ...[
                if (items.any((item) => item['day_of_week'] == entry.key)) ...[
                  Padding(
                    padding: const EdgeInsets.only(top: 12, bottom: 8),
                    child: Text(
                      entry.value.toUpperCase(),
                      style: TextStyle(
                        color: colors.primary,
                        letterSpacing: 1.2,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  ...items
                      .where((item) => item['day_of_week'] == entry.key)
                      .map(
                        (item) => InkWell(
                          onTap: () => _edit(item),
                          borderRadius: BorderRadius.circular(18),
                          child: InfoCard(
                            icon: Icons.menu_book_rounded,
                            title: item['subject'] as String,
                            subtitle:
                                '${timeLabel(item['start_time'])} – ${timeLabel(item['end_time'])}${item['room'] == null || item['room'] == '' ? '' : ' · ${item['room']}'}${item['faculty'] == null || item['faculty'] == '' ? '' : '\n${item['faculty']}'}',
                            trailing: IconButton(
                              onPressed: () =>
                                  _delete(int.parse(item['id'].toString())),
                              icon: const Icon(Icons.delete_outline),
                            ),
                          ),
                        ),
                      ),
                ],
              ],
          ],
        );
      },
    );
  }
}

class ScheduleDialog extends StatefulWidget {
  const ScheduleDialog({this.item, super.key});

  final Map<String, dynamic>? item;

  @override
  State<ScheduleDialog> createState() => _ScheduleDialogState();
}

class _ScheduleDialogState extends State<ScheduleDialog> {
  static const _days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
  late final TextEditingController _subject;
  late final TextEditingController _room;
  late final TextEditingController _faculty;
  late String _day;
  late TimeOfDay _start;
  late TimeOfDay _end;
  bool _busy = false;

  TimeOfDay _readTime(Object? value, TimeOfDay fallback) {
    if (value == null) return fallback;
    final parts = value.toString().split(':');
    return TimeOfDay(hour: int.parse(parts[0]), minute: int.parse(parts[1]));
  }

  String _time24(TimeOfDay value) =>
      '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

  @override
  void initState() {
    super.initState();
    final item = widget.item;
    _subject = TextEditingController(text: item?['subject'] as String? ?? '');
    _room = TextEditingController(text: item?['room'] as String? ?? '');
    _faculty = TextEditingController(text: item?['faculty'] as String? ?? '');
    _day = item?['day_of_week'] as String? ?? 'MON';
    _start = _readTime(
      item?['start_time'],
      const TimeOfDay(hour: 9, minute: 0),
    );
    _end = _readTime(item?['end_time'], const TimeOfDay(hour: 10, minute: 0));
  }

  @override
  void dispose() {
    _subject.dispose();
    _room.dispose();
    _faculty.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_subject.text.trim().isEmpty) {
      showMessage(context, 'Enter a subject name.');
      return;
    }
    setState(() => _busy = true);
    try {
      await ApiClient.post('schedule.php', {
        'action': 'save',
        if (widget.item != null) 'id': int.parse(widget.item!['id'].toString()),
        'subject': _subject.text.trim(),
        'day_of_week': _day,
        'start_time': _time24(_start),
        'end_time': _time24(_end),
        'room': _room.text.trim(),
        'faculty': _faculty.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.item == null ? 'Add class' : 'Edit class'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _subject,
              decoration: const InputDecoration(labelText: 'Subject'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _day,
              decoration: const InputDecoration(labelText: 'Day'),
              items: [
                for (var index = 0; index < _days.length; index++)
                  DropdownMenuItem(
                    value: _days[index],
                    child: Text(
                      const [
                        'Monday',
                        'Tuesday',
                        'Wednesday',
                        'Thursday',
                        'Friday',
                        'Saturday',
                        'Sunday',
                      ][index],
                    ),
                  ),
              ],
              onChanged: (value) => setState(() => _day = value ?? 'MON'),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextButton(
                    onPressed: () async {
                      final value = await showTimePicker(
                        context: context,
                        initialTime: _start,
                      );
                      if (value != null) setState(() => _start = value);
                    },
                    child: Text('Start ${_start.format(context)}'),
                  ),
                ),
                Expanded(
                  child: TextButton(
                    onPressed: () async {
                      final value = await showTimePicker(
                        context: context,
                        initialTime: _end,
                      );
                      if (value != null) setState(() => _end = value);
                    },
                    child: Text('End ${_end.format(context)}'),
                  ),
                ),
              ],
            ),
            TextField(
              controller: _room,
              decoration: const InputDecoration(labelText: 'Room (optional)'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _faculty,
              decoration: const InputDecoration(
                labelText: 'Faculty (optional)',
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _busy ? null : _save,
          child: const Text('Save'),
        ),
      ],
    );
  }
}

class CalendarPage extends StatefulWidget {
  const CalendarPage({super.key});

  @override
  State<CalendarPage> createState() => _CalendarPageState();
}

class _CalendarPageState extends State<CalendarPage> {
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);
  late Future<List<Map<String, dynamic>>> _data;

  @override
  void initState() {
    super.initState();
    _data = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async =>
      List<Map<String, dynamic>>.from(
        await ApiClient.get(
          'calendar.php',
          query: {
            'month': _month.month.toString(),
            'year': _month.year.toString(),
          },
        ) as List,
      );

  Future<void> _changeMonth(int offset) async {
    setState(() {
      _month = DateTime(_month.year, _month.month + offset);
      _data = _load();
    });
  }

  Future<void> _addEvent() async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => const EventDialog(),
    );
    if (saved == true && mounted) setState(() => _data = _load());
  }

  Future<void> _delete(int id) async {
    try {
      await ApiClient.post('calendar.php', {'action': 'delete', 'id': id});
      if (mounted) setState(() => _data = _load());
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _data,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return ErrorState(
            error: snapshot.error,
            retry: () async => setState(() => _data = _load()),
          );
        }
        if (!snapshot.hasData) return const LoadingState();
        final events = snapshot.data!;
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            PageHeading(
              title: 'Calendar',
              subtitle: 'Events and important dates',
              action: IconButton.filled(
                onPressed: _addEvent,
                icon: const Icon(Icons.add),
                tooltip: 'Add event',
              ),
            ),
            Card(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    IconButton(
                      onPressed: () => _changeMonth(-1),
                      icon: const Icon(Icons.chevron_left),
                    ),
                    Text(
                      '${monthName(_month.month)} ${_month.year}',
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    IconButton(
                      onPressed: () => _changeMonth(1),
                      icon: const Icon(Icons.chevron_right),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 18),
            const SectionTitle(title: 'This month'),
            const SizedBox(height: 10),
            if (events.isEmpty)
              const EmptyCard(
                message: 'No events this month. Add a deadline or reminder.',
              )
            else
              ...events.map(
                (event) => Dismissible(
                  key: ValueKey('event-${event['id']}'),
                  direction: event['source'] == 'manual'
                      ? DismissDirection.endToStart
                      : DismissDirection.none,
                  background: const DeleteBackground(),
                  onDismissed: (_) =>
                      _delete(int.parse(event['id'].toString())),
                  child: InfoCard(
                    icon: Icons.event_rounded,
                    title: event['title'] as String,
                    subtitle:
                        '${dateLabel(DateTime.parse(event['event_date'] as String))}${event['event_time'] == null ? '' : ' · ${timeLabel(event['event_time'])}'}${event['description'] == null || event['description'] == '' ? '' : '\n${event['description']}'}',
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class EventDialog extends StatefulWidget {
  const EventDialog({super.key});

  @override
  State<EventDialog> createState() => _EventDialogState();
}

class _EventDialogState extends State<EventDialog> {
  final _title = TextEditingController();
  final _description = TextEditingController();
  DateTime _date = DateTime.now();
  TimeOfDay? _time;
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_title.text.trim().isEmpty) {
      showMessage(context, 'Enter an event title.');
      return;
    }
    setState(() => _busy = true);
    try {
      await ApiClient.post('calendar.php', {
        'action': 'save',
        'title': _title.text.trim(),
        'event_date': dateKey(_date),
        'event_time': _time == null
            ? ''
            : '${_time!.hour.toString().padLeft(2, '0')}:${_time!.minute.toString().padLeft(2, '0')}',
        'description': _description.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Add event'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _title,
              decoration: const InputDecoration(labelText: 'Title'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _description,
              decoration: const InputDecoration(labelText: 'Description'),
            ),
            TextButton.icon(
              onPressed: () async {
                final value = await showDatePicker(
                  context: context,
                  initialDate: _date,
                  firstDate: DateTime(2000),
                  lastDate: DateTime(2100),
                );
                if (value != null) setState(() => _date = value);
              },
              icon: const Icon(Icons.calendar_today),
              label: Text(dateLabel(_date)),
            ),
            TextButton.icon(
              onPressed: () async {
                final value = await showTimePicker(
                  context: context,
                  initialTime: _time ?? TimeOfDay.now(),
                );
                if (value != null) setState(() => _time = value);
              },
              icon: const Icon(Icons.schedule),
              label: Text(_time?.format(context) ?? 'Add time (optional)'),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _busy ? null : _save,
          child: const Text('Save'),
        ),
      ],
    );
  }
}

class PageHeading extends StatelessWidget {
  const PageHeading({
    required this.title,
    required this.subtitle,
    this.action,
    super.key,
  });

  final String title;
  final String subtitle;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: TextStyle(color: colors.onSurfaceVariant),
                ),
              ],
            ),
          ),
          ?action,
        ],
      ),
    );
  }
}

class SummaryCard extends StatelessWidget {
  const SummaryCard({
    required this.label,
    required this.value,
    required this.color,
    this.icon,
    super.key,
  });

  final String label;
  final String value;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Row(
          children: [
            if (icon != null) ...[
              CircleAvatar(
                radius: 19,
                backgroundColor: color.withValues(alpha: 0.13),
                child: Icon(icon, size: 18, color: color),
              ),
              const SizedBox(width: 11),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: TextStyle(
                      color: colors.onSurfaceVariant,
                      fontSize: 12,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 5),
                  Text(
                    value,
                    style: TextStyle(
                      color: color,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class BalanceCard extends StatelessWidget {
  const BalanceCard({required this.balance, super.key});

  final double balance;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      color: colors.primary,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'MONTHLY BALANCE',
                    style: TextStyle(
                      color: colors.onPrimary.withValues(alpha: 0.75),
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.1,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    money(balance),
                    style: TextStyle(
                      color: colors.onPrimary,
                      fontSize: 27,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Income minus expenses this month',
                    style: TextStyle(
                      color: colors.onPrimary.withValues(alpha: 0.78),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Icon(
              Icons.account_balance_wallet_rounded,
              color: colors.onPrimary.withValues(alpha: 0.85),
              size: 32,
            ),
          ],
        ),
      ),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle({required this.title, this.trailing, super.key});

  final String title;
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
          ),
        ),
        if (trailing != null)
          Text(trailing!, style: TextStyle(color: colors.onSurfaceVariant)),
      ],
    );
  }
}

class InfoCard extends StatelessWidget {
  const InfoCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.onTap,
    this.trailing,
    this.iconColor,
    super.key,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  final Widget? trailing;
  final Color? iconColor;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final resolvedIconColor = iconColor ?? colors.primary;
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: resolvedIconColor.withValues(alpha: 0.13),
          child: Icon(icon, color: resolvedIconColor, size: 20),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(
          subtitle,
          style: TextStyle(color: colors.onSurfaceVariant),
        ),
        trailing: trailing,
        onTap: onTap,
      ),
    );
  }
}

class EmptyCard extends StatelessWidget {
  const EmptyCard({required this.message, super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Text(message, style: TextStyle(color: muted)),
      ),
    );
  }
}

class LoadingState extends StatelessWidget {
  const LoadingState({super.key});

  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator());
}

class ErrorState extends StatelessWidget {
  const ErrorState({required this.error, required this.retry, super.key});

  final Object? error;
  final VoidCallback retry;

  @override
  Widget build(BuildContext context) {
    final errorColor = Theme.of(context).colorScheme.error;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.cloud_off, color: errorColor, size: 36),
            const SizedBox(height: 12),
            Text(error.toString(), textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: retry, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }
}

class DeleteBackground extends StatelessWidget {
  const DeleteBackground({super.key});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      alignment: Alignment.centerRight,
      padding: const EdgeInsets.only(right: 20),
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: colors.error,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Icon(Icons.delete_outline, color: colors.onError),
    );
  }
}

double number(Object? value) => double.tryParse(value?.toString() ?? '') ?? 0;

String money(double value) => '₱${value.toStringAsFixed(2)}';

String dateKey(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

String dateLabel(DateTime date) =>
    '${date.day} ${monthName(date.month)} ${date.year}';

String monthName(int month) => const [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
][month - 1];

String timeLabel(Object? time) {
  if (time == null) return '';
  final parts = time.toString().split(':');
  if (parts.length < 2) return time.toString();
  final hour = int.tryParse(parts[0]) ?? 0;
  final minute = parts[1];
  final suffix = hour >= 12 ? 'PM' : 'AM';
  final hour12 = hour % 12 == 0 ? 12 : hour % 12;
  return '$hour12:$minute $suffix';
}

void showMessage(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
